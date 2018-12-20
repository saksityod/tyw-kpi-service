<?php
 
namespace App\Http\Controllers;

use App\EmpLevel;
use App\Employee;

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

class ImportEmployeeController extends Controller
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
					'employee_name' => 'required|max:255',
					'working_start_date_yyyy_mm_dd' => 'date|date_format:Y-m-d',
					'probation_end_date_yyyy_mm_dd' => 'date|date_format:Y-m-d',
					'acting_end_date_yyyy_mm_dd' => 'date|date_format:Y-m-d',
					'department_code' => 'max:50',
					'department_name' => 'max:255',
					'section_code' => 'max:50',
					'section_name' => 'max:255',
					'position_code' => 'max:50',
					'position_name' => 'max:255',
					'position_group' => 'max:255',
					'supervisor_employee_code' => 'max:50',	
					'salary_amount' => 'required|numeric|digits_between:1,10',
					'erp_user' => 'max:50',	
					'email' => 'required|email|max:100',	
					'emp_type' => 'max:50',
				]);

				if ($validator->fails()) {
					$errors[] = ['employee_code' => $i->employee_code, 'errors' => $validator->errors()];
				} else {
					$emp = Employee::find($i->employee_code);
					if (empty($emp)) {
						$emp = new Employee;
						$emp->emp_code = $i->employee_code;
						$emp->emp_name = $i->employee_name;
						$emp->working_start_date = $i->working_start_date_yyyy_mm_dd;
						$emp->probation_end_date = $i->probation_end_date_yyyy_mm_dd;
						$emp->acting_end_date = $i->acting_end_date_yyyy_mm_dd;
						$emp->department_code = $i->department_code;
						$emp->department_name = $i->department_name;
						$emp->section_code = $i->section_code;
						$emp->section_name = $i->section_name;
						$emp->position_code = $i->position_code;
						$emp->position_name = $i->position_name;
						$emp->position_group = $i->position_group;
						$emp->chief_emp_code = $i->supervisor_employee_code;
						$emp->s_amount = $i->salary_amount;
						$emp->erp_user = $i->erp_user;
						$emp->email = $i->email;
						$emp->emp_type = $i->employee_type;
						$emp->is_active = 1;
						$emp->is_coporate_kpi = 0;						
						$emp->created_by = Auth::id();
						$emp->updated_by = Auth::id();
						try {
							$emp->save();
						} catch (Exception $e) {
							$errors[] = ['employee_code' => $i->employee_code, 'errors' => substr($e,0,254)];
						}
					} else {
						$emp->emp_name = $i->employee_name;
						$emp->working_start_date = $i->working_start_date_yyyy_mm_dd;
						$emp->probation_end_date = $i->probation_end_date_yyyy_mm_dd;
						$emp->acting_end_date = $i->acting_end_date_yyyy_mm_dd;
						$emp->department_code = $i->department_code;
						$emp->department_name = $i->department_name;
						$emp->section_code = $i->section_code;
						$emp->section_name = $i->section_name;
						$emp->position_code = $i->position_code;
						$emp->position_name = $i->position_name;
						$emp->position_group = $i->position_group;
						$emp->chief_emp_code = $i->supervisor_employee_code;
						$emp->s_amount = $i->salary_amount;
						$emp->erp_user = $i->erp_user;
						$emp->email = $i->email;
						$emp->emp_type = $i->employee_type;
						$emp->is_active = 1;
						$emp->is_coporate_kpi = 0;
						$emp->updated_by = Auth::id();
						try {
							$emp->save();
						} catch (Exception $e) {
							$errors[] = ['employee_code' => $i->employee_code, 'errors' => substr($e,0,254)];
						}
					}
				}					
			}
		}
		return response()->json(['status' => 200, 'errors' => $errors]);
	}
	
	public function index(Request $request)
	{	
		$qinput = array();
		$query = "
			select emp_code, emp_name, department_name, section_name, position_name, position_group, chief_emp_code, emp_type
			From employee
			Where 1=1
		";
				
		empty($request->department_code) ?: ($query .= " AND department_code = ? " AND $qinput[] = $request->department_code);
		empty($request->section_code) ?: ($query .= " And section_code = ? " AND $qinput[] = $request->section_code);
		empty($request->position_code) ?: ($query .= " And position_code = ? " AND $qinput[] = $request->position_code);
		empty($request->emp_code) ?: ($query .= " And emp_code = ? " AND $qinput[] = $request->emp_code);
		
		$qfooter = " Order by emp_code ";
		
		$items = DB::select($query . $qfooter, $qinput);
		
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		foreach ($itemsForCurrentPage as $i) {
			$al = DB::select("
				select a.appraisal_level_id, b.appraisal_level_name
				from emp_level a
				left outer join appraisal_level b
				on a.appraisal_level_id = b.appraisal_level_id
				where a.emp_code = ?
			", array($i->emp_code));
			$i->appraisal_level = $al;
		}
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			


		return response()->json($result);
	}
	
    public function role_list()
    {
		$items = DB::select("
			select appraisal_level_id, appraisal_level_name
			from appraisal_level
			where is_active = 1
			order by appraisal_level_name
		");
		return response()->json($items);
    }
	
	public function dep_list()
	{
		$items = DB::select("
			Select distinct department_code, department_name
			From employee
			Order by department_name	
		");
		return response()->json($items);
	}
   
    public function sec_list(Request $request)
    {

		$qinput = array();
		$query = "
			Select distinct section_code, section_name
			From employee
			Where 1=1
		";
		
		$qfooter = " Order by section_name ";

		empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);				
    }
	
	public function auto_position_name(Request $request)
	{	
		$qinput = array();
		$query = "
			Select distinct position_code, position_name
			From employee
			Where position_name like ?
		";
		
		$qfooter = " Order by position_name limit 10";
		$qinput[] = '%'.$request->position_name.'%';
		empty($request->section_code) ?: ($query .= " and section_code = ? " AND $qinput[] = $request->section_code);
		empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);		
	}
	
	public function auto_employee_name(Request $request)
	{
		$qinput = array();
		$query = "
			Select emp_code, emp_name
			From employee
			Where emp_name like ?
		";
		
		$qfooter = " Order by emp_name limit 10 ";
		$qinput[] = '%'.$request->emp_name.'%';
		empty($request->section_code) ?: ($query .= " and section_code = ? " AND $qinput[] = $request->section_code);
		empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
		empty($request->position_code) ?: ($query .= " and position_code = ? " AND $qinput[] = $request->position_code);
		
		$items = DB::select($query.$qfooter,$qinput);
		return response()->json($items);			
	}
	
	
	public function show($emp_code)
	{
		try {
			$item = Employee::findOrFail($emp_code);
			$item->working_start_date = ($item->working_start_date == '0000-00-00') ? NULL : $item->working_start_date;
			$item->probation_end_date = ($item->probation_end_date == '0000-00-00') ? NULL : $item->probation_end_date;
			$item->acting_end_date = ($item->acting_end_date == '0000-00-00') ? NULL : $item->acting_end_date;
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Employee not found.']);
		}
		return response()->json($item);
	}
	
	public function update(Request $request, $emp_code)
	{
		try {
			$item = Employee::findOrFail($emp_code);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Employee not found.']);
		}	
		
        $validator = Validator::make($request->all(), [
			'emp_code' => 'required|max:50|unique:employee,emp_code,'. $emp_code . ',emp_code',
			'emp_name' => 'required|max:255',
			'working_start_date' => 'date|date_format:Y-m-d',
			'probation_end_date' => 'date|date_format:Y-m-d',
			'acting_end_date' => 'date|date_format:Y-m-d',
			'department_code' => 'max:50',
			'department_name' => 'max:255',
			'section_code' => 'max:50',
			'section_name' => 'max:255',
			'position_code' => 'max:50',
			'position_name' => 'max:255',
			'position_group' => 'max:255',
			'chief_emp_code' => 'max:50',	
			's_amount' => 'required|numeric|digits_between:1,10',
			'erp_user' => 'max:50',	
			'email' => 'required|email|max:100',	
			'emp_type' => 'max:50',
			'is_active' => 'required|boolean',	
			'is_coporate_kpi' => 'required|boolean',	
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'data' => $validator->errors()]);
        } else {
			$item->fill($request->all());
			$item->updated_by = Auth::id();
			$item->save();
		}		
		
		return response()->json(['status' => 200, 'data' => $item]);
	}
	
	public function show_role($emp_code)
	{
		$items = DB::select("
			SELECT a.appraisal_level_id, a.appraisal_level_name, if(b.emp_code is null,0,1) role_active
			FROM appraisal_level a
			left outer join emp_level b
			on a.appraisal_level_id = b.appraisal_level_id
			and b.emp_code = ?
			order by a.appraisal_level_name		
		", array($emp_code));
		return response()->json($items);
	}
	
	public function assign_role(Request $request, $emp_code)
	{
		DB::table('emp_level')->where('emp_code',$emp_code)->delete();
		
		if (empty($request->roles)) {
		} else {
			foreach ($request->roles as $r) {
				$item = new EmpLevel;
				$item->appraisal_level_id = $r;
				$item->emp_code = $emp_code;
				$item->created_by = Auth::id();
				$item->save();
			}		
		}
		
		return response()->json(['status' => 200]);
	}
	
	public function batch_role(Request $request)
	{
		if (empty($request->employees)) {
		} else {
			foreach ($request->employees as $e) {
				DB::table('emp_level')->where('emp_code',$e)->delete();
				if (empty($request->roles)) {
				} else {
					foreach ($request->roles as $r) {
						$item = new EmpLevel;
						$item->appraisal_level_id = $r;
						$item->emp_code = $e;
						$item->created_by = Auth::id();
						$item->save();
					}				
				}
			}
		}
		return response()->json(['status' => 200]);
	}
	
	public function destroy($emp_code)
	{
		try {
			$item = Employee::findOrFail($emp_code);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 404, 'data' => 'Employee not found.']);
		}	

		try {
			$item->delete();
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Employee is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);
		
	}	
}
