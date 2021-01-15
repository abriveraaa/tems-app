<?php

namespace App\Http\Controllers;

use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Role;
use App\Models\College;
use App\Models\Course;
use App\Models\Borrower;
use App\Models\Category;
use App\Models\Requests;
use App\Models\ToolName;
use App\Models\Tools;
use App\Models\Room;
use App\Models\User;
use App\Models\Lhof;
use DataTables;
use DB;

class CategoryController extends Controller
{
    public function getRole()
    {
        $role = Role::pluck("description","id");
        return response()->json($role);
    }

    public function getCollege()
    {
        $type = College::orderBy('description', 'ASC')
            ->pluck('description', 'id');

        return response()->json($type);
    }

    public function getRoom()
    {
        $type = Room::orderBy('code', 'ASC')
            ->pluck('code', 'id');

        return response()->json($type);
    }

    public function getToolCategories()
    {
        $type = Category::orderBy('description', 'ASC')
            ->pluck('description', 'id');

        return response()->json($type);
    }

    public function getCourse($course)
    {
        if(College::whereId($course)->exists()) {
            $course = College::whereId($course)->with('courses')->first();
    		return response()->json($course);
    	} else {
    		return response()->json([
    			"success" => "Course not found"
    		], 404);
        }
    }

    public function getReportedTools()
    {
        $tools = Tools::onlyTrashed()->with(['toolreport', 'toolcategory', 'toolname', 'toolroom', 'tooladmin'])->get();
        return Datatables::of($tools)
            ->make(true); 
    }

    public function getBorrower($borrower)
    {
        if(Borrower::where('studnum', $borrower)->where('reported_at', null)->exists()) {
            $borrower = Borrower::where('studnum', $borrower)->with(['borrowercollege', 'borrowercourse'])->first();
    		return response()->json($borrower);
    	} else if(Borrower::where('studnum', $borrower)->where('reported_at','<>', null)->exists()){
            return response()->json([
    			"error" => "Borrower is temporarily banned"
    		], 404);
        }else {
    		return response()->json([
    			"error" => "Borrower not found"
    		], 404);
        }
    }

    public function getToolName($toolname)
    {
        if(Category::whereId($toolname)->exists()) {
            $toolname = Category::whereId($toolname)->with('items')->first();
    		return response()->json($toolname);
    	} else {
    		return response()->json([
    			"success" => "Tool name not found"
    		], 404);
        }
    }

    public function getCourseUser($borrower)
    {
        $data = Borrower::find($borrower);
        $res = $data->borrowercourse()->wherePivot('borrower_id', $borrower)->get();
        return response()->json($res);
    }

    public function getCollegeUser($borrower)
    {
        $data = Borrower::find($borrower);
        $res = $data->borrowercollege()->wherePivot('borrower_id', $borrower)->get();
        return response()->json($res);
    }

    public function getLastId($lastid)
    {
        $category = Category::where('id', $lastid)->first();
		$idcategory = $category->id;
		$num_padded = sprintf("%02d", $idcategory);
		$prefix = 'ITECH-'.$num_padded.'-';
		$barcode = IdGenerator::generate(['table' => 'tools', 'field' => 'barcode', 'length' => 13, 'prefix' =>$prefix, 'reset_on_prefix_change' => true]);
		return response()->json($barcode);
    }

    public function sortCategory()
    {
        $sortcategory = Category::with('tools')->withCount('tools')->get();
        return Datatables::of($sortcategory)
            ->addIndexColumn()
            ->addColumn('action', function($row){
                if($row->tools_count > 0){
                    $btn = '<a href="javascript:void(0)" class="view-category btn btn-primary btn-sm mr-2" data-id="'. $row->id .'" data-toggle="modal" data-target="#categorymodal"><i class="fas fa-tv mr-2"></i>View</a>';
                    // $btn .= '<a href="javascript:void(0)" class="print-category btn btn-warning btn-sm" data-id="'. $row->id .'" data-toggle="modal" data-target="#"><i class="fas fa-print mr-2"></i>Print</a>';
                    return $btn; 
                   }else{
                       $btn ='';
                       return $btn;
                   }
            })
            ->rawColumns(['action'])
            ->make(true);      
    }

    public function sortItemName()
    {
        $sortitemname = ToolName::with('tools')->withCount('tools')->get();
        return Datatables::of($sortitemname)
            ->addIndexColumn()
            ->addColumn('action', function($row){
               if($row->tools_count > 0){
                $btn = '<a href="javascript:void(0)" class="view-itemname btn btn-primary btn-sm mr-2" data-id="'. $row->id .'" data-toggle="modal" data-target="#categorymodal"><i class="fas fa-tv mr-2"></i>View</a>';
                // $btn .= '<a href="javascript:void(0)" class="print-category btn btn-warning btn-sm" data-id="'. $row->id .'" data-toggle="modal" data-target="#"><i class="fas fa-print mr-2"></i>Print</a>';
                return $btn; 
               }else{
                   $btn ='';
                   return $btn;
               }
            })
            ->rawColumns(['action'])
            ->make(true);      
    }

    public function toolCategory()
    {
        $tools = Tools::with(['toolcategory', 'toolname', 'toolroom', 'tooladmin'])->get();

        return Datatables::of($tools)
            ->make(true);   
    }

    public function itemName()
    {
        $tools = Tools::with(['toolcategory', 'toolname', 'toolroom', 'tooladmin'])->get();
        return Datatables::of($tools)
            ->make(true);   
    }

    public function countActiveBorrower()
    {
        $active = Borrower::where('reported_at', '=', null)->count();

        return response()->json($active);
    }

    public function countBannedBorrower()
    {
        $banned = Borrower::where('reported_at', '<>', null)->count();

        return response()->json($banned);
    }

    public function countServiceableItems()
    {
        $tools = Tools::where('deleted_at', '=', null)->count();

        return response()->json($tools);
    }

    public function countNewItem()
    {
    	$today = Carbon::now()->toDateString();
    	$newpurchaseitem = Tools::where('created_at', 'rlike', $today)->count('created_at');
    	return response()->json($newpurchaseitem);
    }

    public function getLastLHOF()
	{
		$prefix = Carbon::now()->format('y').'-';  
        $lhof = IdGenerator::generate(['table' => 'lhof', 'field' => 'code', 'length' => 7, 'prefix' =>$prefix, 'reset_on_prefix_change' => true]);
		return response()->json($lhof);
    }
    
    public function getLastIdLhof()
    {
    	$previous = Lhof::latest('created_at')->first();
		if($previous == null || $previous == '')
		{
			$last = 1;
		} else {
			$last = $previous->id+1;
		}

    	return response()->json($last);
    }

    public function toolBarcode($barcode)
    {
        if(Tools::where('barcode', $barcode)->where('reason', null)->exists()) {
            $tool = Tools::select(['id', 'barcode', 'reason'])->where('barcode', $barcode)->where('reason', null)->with(['toolcategory', 'toolname', 'toolroom'])->first();
    		return response()->json(["status" => true, "data" => $tool]);
    	} else {
    		return response()->json([
    			"status" => false
    		], 404);
        }
    }

    public function activeItem()
    {
        $data = Tools::with(['tooladmin', 'toolcategory', 'toolname', 'toolroom'])->get();

        return response()->json($data);
    }

    public function reportedItem()
    {
        $data = Tools::onlyTrashed()->with(['tooladmin', 'toolcategory', 'toolname', 'toolroom', 'toolreport'])->get();

        return response()->json($data);
    }

    public function activeBorrower()
    {
        $active = Borrower::where('reported_at', '=', null)->with(['borrowercourse'])->get();

        return response()->json($active);
    }

    public function bannedBorrower()
    {
        $banned = Borrower::where('reported_at', '<>', null)->with(['borrowercourse'])->get();

        return response()->json($banned);
    }

    public function getUsageCount(Request $request)
	{
		$startdate = $request->start;
		$enddate = $request->end;

		$start = Carbon::parse($startdate)->format('Y-m-d ').'00:00:00';
        $end = Carbon::parse($enddate)->format('Y-m-d ').'23:59:59';

        $data = DB::SELECT("SELECT requests.created_at, tool_names.id, tool_names.description, COUNT( tool_names.id ) AS count
        FROM tool_names
        INNER JOIN request_item ON tool_names.id = request_item.tool_name_id
        INNER JOIN requests ON requests.id = request_item.requests_id
        WHERE requests.status = 'Returned' AND requests.created_at BETWEEN '$start' AND '$end'
        GROUP BY tool_name_id");
        // $data = Requests::select(\DB::raw('COUNT(tool) as item_count'),'lhof', 'id', 'tool', 'status', 'created_at', 'updated_at')->where('status', 'Returned')->whereBetween('created_at', [$start, $end])->groupBy('tool')->with(['borrower', 'item', 'room', 'borrow'])->toSql();
		return response()->json($data);
    }
    
    // public function inventoryItem(Request $request)
    public function inventoryItem()
    {
        // $startdate = $request->start;
        // $enddate = $request->end;
        $startdate = '01/01/2021';
		$enddate = '01/03/2021';

		$start = Carbon::parse($startdate)->format('Y-m-d ').'00:00:00';
        $end = Carbon::parse($enddate)->format('Y-m-d ').'23:59:59';

        // $result = ToolName::with('tools')->withCount('tools')->get();

        $startnow = Carbon::now()->format('Y-m-d ').'00:00:00';
        $endnow = Carbon::now()->format('Y-m-d ').'23:59:59';
        
        $prioqty = DB::SELECT("SELECT categories.description, COUNT( categories.id ) AS priorqty
                        FROM categories
                        INNER JOIN tool_category ON categories.id = tool_category.category_id
                        INNER JOIN tools ON tools.id = tool_category.tools_id
                        WHERE tools.created_at NOT BETWEEN '$start' AND '$end'
                        GROUP BY category_id
                    ");
        
        $category = DB::SELECT("SELECT categories.id, categories.description FROM categories
                            INNER JOIN tool_category ON categories.id = tool_category.category_id
                            INNER JOIN tools ON tools.id = tool_category.tools_id
                            WHERE tools.created_at BETWEEN '$start' AND '$end'
                            GROUP BY categories.id
                        ");
        
        $itemname = DB::SELECT("SELECT tool_names.description FROM tool_names
                            INNER JOIN tool_toolnames ON tool_names.id = tool_toolnames.tool_name_id
                            INNER JOIN tools ON tools.id = tool_toolnames.tools_id
                            WHERE tools.created_at BETWEEN '$start' AND '$end'
                            GROUP BY tool_name_id
                        ");

        $addedqty = DB::SELECT("SELECT tool_names.description, COUNT( tool_names.id ) AS added_qty
                            FROM tool_names
                            INNER JOIN tool_toolnames ON tool_names.id = tool_toolnames.tool_name_id
                            INNER JOIN tools ON tools.id = tool_toolnames.tools_id
                            WHERE tools.created_at BETWEEN '$startnow' AND '$endnow'
                            GROUP BY tool_name_id
                        ");
        
		return response()->json([$prioqty, $category, $itemname, $addedqty]);

    }
}
