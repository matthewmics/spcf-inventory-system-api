<?php

namespace App\Http\Controllers;

use App\Models\BorrowRequest;
use App\Models\InventoryItem;
use App\Models\InventoryParentItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ActivityLogController;

class InventoryController extends Controller
{
    public function getItemParents()
    {
        // $parentItems = InventoryParentItem::with(['inventory_items' => function ($query) {
        //     $query->select('inventory_parent_item_id', 'id')->where('is_disposed', false);
        // }])
        //     ->orderBy('id')->get();

        $parentItems = InventoryParentItem::orderBy('id')->get();

        return $parentItems;
    }

    public function getAvailableItemParents()
    {
        // $parentItems = InventoryParentItem::with(['inventory_items' => function ($query) {
        //     $query->select('inventory_parent_item_id', 'id')->whereNull('room_id')->where('is_disposed', false);
        // }])
        //     ->orderBy('id')->get();

        $parentItems = InventoryParentItem::orderBy('id')->get();

        return $parentItems;
    }

    public function getAvailableItems($inventory_parent_item_id)
    {
        return InventoryItem::with(['room', 'transfer_requests' => function ($query) {
            $query->whereNotIn('status', ['completed', 'rejected']);
        }, 'repair_requests' => function ($query) {
            $query->whereNotIn('status', ['completed', 'rejected', 'disposed', 'replaced', 'PO created']);
        }])
            ->orderBy('created_at')
            ->where('inventory_parent_item_id', $inventory_parent_item_id)
            ->whereNull('room_id')
            ->where('is_disposed', false)
            ->orderBy('id')
            ->get();
    }

    public function getDisposedItems()
    {
        return InventoryItem::with('inventory_parent_item')->where('is_disposed', true)->get();
    }

    public function createItemParent(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'item_type' => 'string|required'
        ]);

        $current_user = auth()->user();

        ActivityLogController::store(auth()->user(), "<b>$current_user->name</b> created Item <b>$request->name</b>");

        return InventoryParentItem::create($request->all());
    }

    public function findItemParent($id)
    {
        return InventoryParentItem::find($id);
    }

    public function updateItemParent(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'item_type' => 'string|required'
        ]);

        $item = InventoryParentItem::find($id);


        $current_user = auth()->user();

        ActivityLogController::store(auth()->user(), "<b>$current_user->name</b> edited Item <b>$item->name</b>");

        $item->update($request->all());

        return $item;
    }

    public function deleteItemParent($id)
    {

        $item = InventoryParentItem::find($id);


        $current_user = auth()->user();

        ActivityLogController::store(auth()->user(), "<b>$current_user->name</b> edited Item <b>$item->name</b>");

        $item->delete();

        return response()->noContent();
    }


    //-- item------------------------------------------------------------------------------=====================


    // item
    public function findItem($id)
    {
        return InventoryItem::find($id);
    }

    //item
    public function getItems($id)
    {
        return InventoryItem::with('room')
            ->with(['available_transfer_requests', 'available_repair_requests'])
            ->where('inventory_parent_item_id', $id)
            ->where('is_disposed', false)
            ->orderBy('created_at')
            ->get();
    }

    //item
    public function inventoryItemShowComponents($id)
    {
        return InventoryItem::with(['components', 'inventory_parent_item', 'components.component'])
            ->where('id', $id)
            ->where('is_disposed', false)
            ->orderBy('created_at')
            ->first();
    }

    //item
    public function createItem(Request $request)
    {
        $request->validate([
            'serial_number' => 'required',
            'brand' => 'required',
            'inventory_parent_item_id' => 'required'
        ]);

        $parent = InventoryParentItem::find($request['inventory_parent_item_id']);

        $current_user = auth()->user();

        ActivityLogController::store(auth()->user(), "<b>$current_user->name</b> created $parent->name <b>$request->serial_number</b>");

        return InventoryItem::create($request->all());
    }

    //item
    public function updateItem(Request $request, $id)
    {

        $request->validate([
            'serial_number' => 'required',
            'brand' => 'required'
        ]);

        $item = InventoryItem::find($id);

        $parent = InventoryParentItem::find($item->inventory_parent_item_id);

        $current_user = auth()->user();

        ActivityLogController::store(auth()->user(), "<b>$current_user->name</b> edited $parent->name <b>$item->serial_number</b>");

        $item->update($request->all());

        return $item;
    }


    // item
    public function deleteItem($id)
    {

        $item = InventoryItem::find($id);

        $parent = InventoryParentItem::find($item->inventory_parent_item_id);

        $current_user = auth()->user();

        ActivityLogController::store(auth()->user(), "<b>$current_user->name</b> deleted $parent->name <b>$item->serial_number</b>");

        $item->delete();
        return response()->noContent();
    }


    // item
    public function disposeItem($id)
    {

        $item = InventoryItem::find($id);
        $item->update([
            'is_disposed' => true,
            'deleted_at' => Carbon::now('UTC'),
            'room_id' => null
        ]);

        return response()->noContent();
    }

    public function setRoom(Request $request, $id)
    {
        $item = InventoryItem::find($id);

        $item->room_id = $request['room_id'];

        $item->save();

        $item->load(['inventory_parent_item']);

        return $item;
    }

    public function allAvailableItems()
    {
        return InventoryItem::with(['inventory_parent_item'])->where('is_disposed', false)->whereNull('room_id')->get();
    }

    public function unavailableItems()
    {
        $result = InventoryItem::with(['inventory_parent_item', 'room', 'room.building'])
            ->where('is_disposed', false)->whereNotNull('room_id')->get();

        $result = $result->filter(function ($model) {
            return $model->borrow_status === 'none' || $model->borrow_status === 'returned';
        })->values();

        // $result = Arr::where($result, function($model){
        //     return !$model->is_borrowed;
        // });

        return $result;
    }

    public function availableForBorrows(Request $request)
    {
        $role = auth()->user()->role;

        $parentId = $request->query('parentId');
        $roomId = $request->query('roomId');

        $query = InventoryItem::with(['inventory_parent_item', 'room', 'room.building'])
            ->where('is_disposed', false)
            ->where('inventory_parent_item_id', $parentId)
            ->where('room_id', $roomId)
            ->whereNotNull('room_id');

        $result = $query->get();

        $result = $result->filter(function ($model) use ($role) {

            $itemType = $model->inventory_parent_item->item_type;

            if ($role === 'its' && $itemType !== 'PC') {
                return false;
            } else if ($role === 'ppfo' && $itemType === 'PC') {
                return false;
            }

            $isAvailable = is_null($model->borrow_request_id)
                && ($model->repair_status === 'none' || $model->repair_status === 'repaired')
                && ($model->transfer_status === 'none' || $model->transfer_status === 'completed');


            return $isAvailable;
        })->values();

        return $result;
    }
}
