<?php

namespace App\Http\Controllers;

use App\CDS;
use App\CDSResult;
use App\PeriodMonth;
use App\SystemConfiguration;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CDSResultController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function import(Request $request)
	{
		$errors = array();
		foreach ($request->file() as $f) {
			$items = Excel::load($f, function($reader){})->get();			
			foreach ($items as $i) {
				
				$validator = Validator::make($i->toArray(), [
					'employee_code' => 'required|max:50',
					'cds_id' => 'required|integer',
					'year' => 'required|integer',
					'month' => 'required|integer',
					'cds_value' => 'required|numeric|digits_between:1,15',
				]);

				if ($validator->fails()) {
					$errors[] = ['employee_code' => $i->employee_code, 'errors' => $validator->errors()];
				} else {
					$month_name = PeriodMonth::find($i->month);
					if (empty($month_name)) {
						$errors[] = ['employee_code' => $i->employee_code, 'errors' => 'Invalid Month.'];
					} else {
						try {
							$result_check = CDSResult::where("emp_code",$i->employee_code)->where("cds_id",$i->cds_id)->where('appraisal_year',$i->year)->where('appraisal_month_no',$i->month);
							
							if ($result_check->count() == 0) {
								$cds_result = new CDSResult;
								$cds_result->emp_code = $i->employee_code;
								$cds_result->cds_id = $i->cds_id;
								$cds_result->appraisal_year = $i->year;
								$cds_result->appraisal_month_no = $i->month;
								$cds_result->appraisal_month_name = $month_name->month_name;
								$cds_result->cds_value = $i->cds_value;
								$cds_result->created_by = Auth::id();
								$cds_result->updated_by = Auth::id();						
								$cds_result->save();							
							} else {
								CDSResult::where("emp_code",$i->employee_code)->where("cds_id",$i->cds_id)->where('appraisal_year',$i->year)->where('appraisal_month_no',$i->month)->update(['cds_value' => $i->cds_value, 'updated_by' => Auth::id()]);							
							}

						} catch (Exception $e) {
							$errors[] = ['employee_code' => $i->employee_code, 'errors' => substr($e,0,254)];
						}
					}
				}					
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}	
	
	public function export(Request $request)
	{
		$qinput = array();

		try {
			$config = SystemConfiguration::firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		}		
		
		// if ($request->current_appraisal_year != $config->current_appraisal_year) {
			// return response()->json(['status' => 400, 'data' => 'Selected Year does not match Current Appraisal Year in System Configuration']);
		// }
		
		$checkyear = DB::select("
			select 1
			from appraisal_period
			where appraisal_year = ?
			and date(?) between start_date and end_date		
		", array($config->current_appraisal_year, $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01'));
		
		if (empty($checkyear)) {
			return 'Appraisal Period not found for the Current Appraisal Year.';
		}
		
		
		
		$query = "
			select distinct r.emp_code, e.emp_name, cds.cds_id, cds.cds_name, ifnull(cr.cds_value,0) as cds_value, ap.appraisal_year
			from appraisal_item_result r
			inner join employee e on r.emp_code = e.emp_code 
			inner join appraisal_item i on r.appraisal_item_id = i.appraisal_item_id
			inner join kpi_cds_mapping m on i.appraisal_item_id = m.appraisal_item_id
			inner join cds on m.cds_id = cds.cds_id
			inner join appraisal_period ap on r.period_id = ap.period_id
			inner join system_config sys on ap.appraisal_year = sys.current_appraisal_year
			left outer join cds_result cr on cds.cds_id = cr.cds_id
			and r.emp_code = cr.emp_code
			and cr.appraisal_year = {$request->current_appraisal_year}
			and cr.appraisal_month_no = {$request->month_id}
			where cds.is_sql = 0	
		";
		
		// $qinput[] = $request->current_appraisal_year;
		// $qinput[] = $request->month_id;
		
		//empty($request->current_appraisal_year) ?: ($query .= " AND appraisal_year = ? " AND $qinput[] = $request->current_appraisal_year);
		//empty($request->month_id) ?: ($query .= " And appraisal_month_no = ? " AND $qinput[] = $request->month_id);
		
		if (!empty($request->current_appraisal_year) && !empty($request->month_id)) {
			$current_date = $request->current_appraisal_year . str_pad($request->month_id,2,'0',STR_PAD_LEFT) . '01';
			$query .= " and date(?) between ap.start_date and ap.end_date ";
			$qinput[] = $current_date;
		}
		
		empty($request->appraisal_level_id) ?: ($query .= " And i.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		empty($request->position_code) ?: ($query .= " And e.position_code = ? " AND $qinput[] = $request->position_code);
		empty($request->emp_code) ?: ($query .= " And e.emp_code = ? " AND $qinput[] = $request->emp_code);
		
		$qfooter = " Order by r.emp_code, cds.cds_id ";
		
		$items = DB::select($query . $qfooter, $qinput);	
		$filename = "CDS_Result";  //. date('dm') .  substr(date('Y') + 543,2,2);
		$x = Excel::create($filename, function($excel) use($items, $filename, $request) {
			$excel->sheet($filename, function($sheet) use($items, $request) {
				
				$sheet->appendRow(array('Employee Code', 'CDS ID', 'CDS Name', 'Year', 'Month', 'CDS Value'));
		
				foreach ($items as $i) {
					// empty($i->appraisal_year) ? $appraisal_year = $request->current_appraisal_year : $appraisal_year = $i->appraisal_year;
					// empty($i->month_id) ? $month_id = $request->month_id : $month_id = $i->month_id;
					// empty($i->cds_value) ? $cds_value = 0 : $cds_value = $i->cds_value;
					
					$sheet->appendRow(array(
						$i->emp_code, 
						$i->cds_id, 
						$i->cds_name, 
						$request->current_appraisal_year, 
						$request->month_id,
						$i->cds_value
						));
				}
			});

		})->export('xls');				
	}
	
	public function year_list()
	{
		$items = DB::select("
			select current_appraisal_year
			from 
			(
				select current_appraisal_year 
				from system_config
				union
				select current_appraisal_year - 1
				from system_config
				union
				select distinct appraisal_year
				from cds_result
			) a
			order by current_appraisal_year desc
		");
		return response()->json($items);
	}
	
	public function month_list()
	{
		$items = DB::select("
			Select month_id, month_name
			From period_month
			Order by month_id
		");
		return response()->json($items);
	}	
	
    public function al_list()
    {
		$items = DB::select("
			select appraisal_level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			order by appraisal_level_name
		");
		return response()->json($items);
    }
		
	public function auto_position_name(Request $request)
	{
		$items = DB::select("
			Select distinct position_code, position_name
			From employee
			Where position_name like ? and is_active = 1
			Order by position_name		
		", array('%'.$request->position_name.'%'));
		return response()->json($items);
	}
	
	public function auto_emp_name(Request $request)
	{
		$items = DB::select("
			Select distinct e.emp_code, e.emp_name
			From employee e, emp_level l
			Where e.emp_code = l.emp_code 
			And e.emp_name like ? and e.is_active = 1
			Order by e.emp_name		
		", array('%'.$request->emp_name.'%'));
		return response()->json($items);
	}
	
	public function index(Request $request)
	{

		$qinput = array();
		$query = "
			select r.cds_result_id, r.emp_code, e.emp_name, l.appraisal_level_name, r.cds_id, s.cds_name, r.appraisal_year, m.month_name, r.cds_value
			From cds_result r, employee e, cds s, appraisal_level l, period_month m
			Where r.cds_id = s.cds_id and r.emp_code = e.emp_code and s.appraisal_level_id = l.appraisal_level_id
			And r.appraisal_month_no = m.month_id
		";
				
		empty($request->current_appraisal_year) ?: ($query .= " AND appraisal_year = ? " AND $qinput[] = $request->current_appraisal_year);
		empty($request->month_id) ?: ($query .= " And appraisal_month_no = ? " AND $qinput[] = $request->month_id);
		empty($request->appraisal_level_id) ?: ($query .= " And s.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
		empty($request->position_code) ?: ($query .= " And e.position_code = ? " AND $qinput[] = $request->position_code);
		empty($request->emp_code) ?: ($query .= " And r.emp_code = ? " AND $qinput[] = $request->emp_code);
		
		$qfooter = " Order by l.appraisal_level_name, r.emp_code, r.cds_id ";
		
		$items = DB::select($query . $qfooter, $qinput);
		
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			


		return response()->json($result);
	}
	
	public function destroy($cds_result_id)
	{
		try {
			$item = CDSResult::findOrFail($cds_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'CDS Result not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this CDS Result is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}		
	
}
