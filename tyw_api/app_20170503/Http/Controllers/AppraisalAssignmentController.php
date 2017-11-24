<?php

namespace App\Http\Controllers;

use App\AppraisalItemResult;
use App\AppraisalFrequency;
use App\AppraisalPeriod;
use App\EmpResult;
use App\EmpResultStage;
use App\WorkflowStage;
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

class AppraisalAssignmentController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function appraisal_type_list()
	{
		$items = DB::select("
			Select appraisal_type_id, appraisal_type_name
			From appraisal_type
			Order by appraisal_type_name
		");
		return response()->json($items);
	}
	
	
	public function new_assign_to(Request $request)
	{
		$items = DB::select("
			SELECT a.stage_id, a.to_appraisal_level_id, b.appraisal_level_name
			FROM workflow_stage a
			left outer join appraisal_level b
			on a.to_appraisal_level_id = b.appraisal_level_id
			where a.stage_id = 1
			union
			SELECT a.stage_id, a.to_appraisal_level_id, b.appraisal_level_name
			FROM workflow_stage a
			left outer join appraisal_level b
			on a.to_appraisal_level_id = b.appraisal_level_id
			where a.from_stage_id = 1
			and a.to_appraisal_level_id = (
				select parent_id
				from appraisal_level
				where appraisal_level_id = ?
			)
		", array($request->appraisal_level_id));
		
		return response()->json($items);
	}
	
	public function new_action_to(Request $request)
	{
		$items = DB::select("	
			select a.stage_id, a.to_action
			from workflow_stage a
			left outer join appraisal_level b
			on a.to_appraisal_level_id = b.appraisal_level_id
			where a.stage_id = ?
		", array($request->stage_id));
		
		return response()->json($items);			
	}
	
	public function edit_assign_to(Request $request)
	{
	
		$al = DB::select("
			select b.appraisal_level_id, b.is_hr
			from emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where a.emp_code = ?
		", array(Auth::id()));
		
		if (empty($al)) {
			$is_hr = null;
			$al_id = null;
		} else {
			$is_hr = $al[0]->is_hr;
			$al_id = $al[0]->appraisal_level_id;
		}		
	
		$items = DB::select("
			select distinct a.to_appraisal_level_id, b.appraisal_level_name
			from workflow_stage a
			left outer join appraisal_level b
			on a.to_appraisal_level_id = b.appraisal_level_id
			where from_stage_id = ?		
			and from_appraisal_level_id = ?
			and stage_id < 17
		", array($request->stage_id, $al_id));
		
		if (empty($items)) {
			$workflow = WorkflowStage::find($request->stage_id);
			empty($workflow->to_stage_id) ? $to_stage_id = "null" : $to_stage_id = $workflow->to_stage_id;
			$items = DB::select("
				select distinct a.to_appraisal_level_id, b.appraisal_level_name
				from workflow_stage a
				left outer join appraisal_level b
				on a.to_appraisal_level_id = b.appraisal_level_id
				where stage_id in ({$to_stage_id})
				and from_appraisal_level_id = ?
				and stage_id < 17
			", array($al_id));
		}
		
		return response()->json($items);	
	}
	
	public function edit_action_to(Request $request)
	{
		$al = DB::select("
			select b.appraisal_level_id, b.is_hr
			from emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where a.emp_code = ?
		", array(Auth::id()));
		
		if (empty($al)) {
			$is_hr = null;
			$al_id = null;
		} else {
			$is_hr = $al[0]->is_hr;
			$al_id = $al[0]->appraisal_level_id;
		}		
		
		$items = DB::select("
			select stage_id, to_action
			from workflow_stage 
			where from_stage_id = ?		
			and to_appraisal_level_id = ?
			and from_appraisal_level_id = ?
			and stage_id < 17
		", array($request->stage_id, $request->to_appraisal_level_id, $al_id));
		
		if (empty($items)) {
			$workflow = WorkflowStage::find($request->stage_id);
			empty($workflow->to_stage_id) ? $to_stage_id = "null" : $to_stage_id = $workflow->to_stage_id;
			$items = DB::select("	
				select a.stage_id, a.to_action
				from workflow_stage a
				left outer join appraisal_level b
				on a.to_appraisal_level_id = b.appraisal_level_id
				where stage_id in ({$to_stage_id})
				and to_appraisal_level_id = ?
				and from_appraisal_level_id = ?
				and stage_id < 17
			", array($request->to_appraisal_level_id, $al_id));
		}
		
		return response()->json($items);	
	}
	
	public function auto_position_name(Request $request)
	{
		$all_emp = DB::select("
			SELECT count(is_all_employee) count_no
			FROM emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where emp_code = ?
			and is_all_employee = 1		
		", array(Auth::id()));

		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				Select distinct position_code, position_name
				From employee
				Where position_name like ? 
				and is_active = 1
				Order by position_name			
			",array('%'.$request->position_name.'%'));
		} else {
			$items = DB::select("
				Select distinct position_code, position_name
				From employee
				Where chief_emp_code = ?
				And position_name like ? 
				and is_active = 1
				Order by position_name			
			", array(Auth::id(),'%'.$request->position_name.'%'));
		}
		return response()->json($items);
	}	
	
    public function al_list()
    {
		$all_emp = DB::select("
			SELECT count(is_all_employee) count_no
			FROM emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where emp_code = ?
			and is_all_employee = 1		
		", array(Auth::id()));
		
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				Select appraisal_level_id, appraisal_level_name
				From appraisal_level 
				Where is_active = 1 
				Order by appraisal_level_name			
			");
		} else {
				// select al.appraisal_level_id, al.appraisal_level_name
				// from emp_level el, appraisal_level al
				// where el.appraisal_level_id = al.appraisal_level_id
				// and el.emp_code = 1
				// union
			$items = DB::select("
				select distinct el.appraisal_level_id, al.appraisal_level_name
				from employee e, emp_level el, appraisal_level al
				where e.emp_code = el.emp_code
				and el.appraisal_level_id = al.appraisal_level_id
				and e.chief_emp_code = ?
				and e.is_active = 1			
			", array(Auth::id()));
			
			$chief_list = array();
			
			$chief_items = DB::select("
				select distinct e.emp_code
				from employee e, emp_level el, appraisal_level al
				where e.emp_code = el.emp_code
				and el.appraisal_level_id = al.appraisal_level_id
				and e.chief_emp_code = ?
				and e.is_active = 1			
			", array(Auth::id()));
			
			foreach ($chief_items as $i) {
				$chief_list[] = $i->emp_code;
			}
		
			$chief_list = array_unique($chief_list);
			
			// Get array keys
			$arrayKeys = array_keys($chief_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_chief = '';
			foreach($chief_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_chief .= $v;
				} else {
					$in_chief .= $v . ',';
				}
			}					
			
			
			do {				
				empty($in_chief) ? $in_chief = "null" : null;
				$ritems = DB::select("
					select distinct el.appraisal_level_id, al.appraisal_level_name
					from employee e, emp_level el, appraisal_level al
					where e.emp_code = el.emp_code
					and el.appraisal_level_id = al.appraisal_level_id
					and e.is_active = 1		
					and e.chief_emp_code in ({$in_chief})
				");

				$chief_list = array();			
				
				foreach ($ritems as $r) {
					$items[] = $r;
				}
				
				$chief_items = DB::select("
					select distinct e.emp_code
					from employee e, emp_level el, appraisal_level al
					where e.emp_code = el.emp_code
					and el.appraisal_level_id = al.appraisal_level_id
					and e.chief_emp_code in ({$in_chief})
					and e.is_active = 1			
				");
				
				foreach ($chief_items as $i) {
					$chief_list[] = $i->emp_code;
				}			
				
				$chief_list = array_unique($chief_list);
				
				// Get array keys
				$arrayKeys = array_keys($chief_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_chief = '';
				foreach($chief_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_chief .= $v;
					} else {
						$in_chief .= $v . ',';
					}
				}		
			} while (!empty($chief_list));
	
		}			
		
		$items = array_unique($items,SORT_REGULAR);
		
		return response()->json($items);
    }
	
    // public function al_list()
    // {
		// $all_emp = DB::select("
			// SELECT count(is_all_employee) count_no
			// FROM emp_level a
			// left outer join appraisal_level b
			// on a.appraisal_level_id = b.appraisal_level_id
			// where emp_code = ?
			// and is_all_employee = 1		
		// ", array(Auth::id()));
		
		// if ($all_emp[0]->count_no > 0) {
			// $items = DB::select("
				// Select appraisal_level_id, appraisal_level_name
				// From appraisal_level 
				// Where is_active = 1 
				// and is_hr = 0
				// Order by appraisal_level_name			
			// ");
		// } else {
			// $items = DB::select("
				// select distinct el.appraisal_level_id, al.appraisal_level_name
				// from employee e, emp_level el, appraisal_level al
				// where e.emp_code = el.emp_code
				// and el.appraisal_level_id = al.appraisal_level_id
				// and e.chief_emp_code = ?
				// and e.is_active = 1			
			// ", array(Auth::id()));
		// }
		
		// return response()->json($items);
    // }
		
	public function frequency_list()
	{
		$items = DB::select("
			Select frequency_id, frequency_name, frequency_month_value
			From  appraisal_frequency
			Order by frequency_month_value asc
		");
		return response()->json($items);
	}
	
	public function period_list (Request $request)
	{
		// if ($request->assignment_frequency == 1) {
			// $items = DB::select("
				// select period_id, appraisal_period_desc 
				// From appraisal_period
				// Where appraisal_year = (select current_appraisal_year from system_config)		
				// order by appraisal_period_desc
			// ");
		// } else {
			// $items = DB::select("
				// select period_id, appraisal_period_desc 
				// From appraisal_period
				// Where appraisal_year = (select current_appraisal_year from system_config)
				// And appraisal_frequency_id = ?
				// order by appraisal_period_desc
			// ", array($request->frequency_id));
		// }
		$items = DB::select("
			select period_id, appraisal_period_desc 
			From appraisal_period
			Where appraisal_year = ?
			And appraisal_frequency_id = ?
			order by start_date asc
		", array($request->appraisal_year, $request->frequency_id));		
		return response()->json($items);
	}
	
	public function auto_employee_name(Request $request)
	{
		$all_emp = DB::select("
			SELECT count(is_all_employee) count_no
			FROM emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where emp_code = ?
			and is_all_employee = 1		
		", array(Auth::id()));
		if ($all_emp[0]->count_no > 0) {
			$items = DB::select("
				Select emp_code, emp_name
				From employee 
				Where emp_name like ? 
				and is_active = 1
				Order by emp_name			
			", array('%'.$request->emp_name.'%'));
		} else {
			$items = DB::select("
				Select emp_code, emp_name
				From employee 
				Where chief_emp_code = ?
				And emp_name like ?
				and is_active = 1
				Order by emp_name	
			", array(Auth::id(),'%'.$request->emp_name.'%'));
		}
		return response()->json($items);
		
	}
	
	public function index(Request $request)
	{
		$all_emp = DB::select("
			SELECT count(is_all_employee) count_no
			FROM emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where emp_code = ?
			and is_all_employee = 1		
		", array(Auth::id()));

		$qinput = array();
		if ($all_emp[0]->count_no > 0) {
			$query_unassign = "
				Select distinct null as emp_result_id,  'Unassigned' as status, emp_code, emp_name, department_name, section_name, 'N/A' as appraisal_type_name, position_name, null appraisal_type_id, 0 period_id, 'Unassigned' appraisal_period_desc, is_coporate_kpi
				From employee
				Where is_active = 1
			";
			empty($request->position_code) ?: ($query_unassign .= " and position_code = ? " AND $qinput[] = $request->position_code);
			empty($request->department_code) ?: ($query_unassign .= " and department_code = ? " AND $qinput[] = $request->department_code);
			empty($request->emp_code) ?: ($query_unassign .= " and emp_code = ? " AND $qinput[] = $request->emp_code);
			empty($request->appraisal_level_id) ?: ($query_unassign .= " And emp_code in (select emp_code from emp_level where appraisal_level_id = ?) " AND $qinput[] = $request->appraisal_level_id);			
			
			$query_unassign .= "
				and emp_code not in 
				(SELECT emp_code
					FROM   (SELECT e.emp_code,
								   p.appraisal_year,
								   p.appraisal_frequency_id,
								   er.appraisal_type_id,
								   Count(1) assigned_total,
								   z.period_total
							FROM   emp_result er,
								   employee e,
								   appraisal_period p,
								   (SELECT appraisal_year,
										   appraisal_frequency_id,
										   Count(1) period_total
									FROM   appraisal_period
				";
			empty($request->period_id) ?: ($query_unassign .= " where period_id = ? " AND $qinput[] = $request->period_id);
			$query_unassign .= "
									GROUP  BY appraisal_year,
											  appraisal_frequency_id) z
							WHERE  er.emp_code = e.emp_code
								   AND er.period_id = p.period_id
								   AND p.appraisal_year = z.appraisal_year
								   AND p.appraisal_frequency_id = z.appraisal_frequency_id
			";
			//empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
			//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
			//empty($request->appraisal_level_id) ?: ($query_unassign .= " and I.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
			empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
			empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
			empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);
			//empty($request->period_id) ?: ($query_unassign .= " and er.period_id = ? " AND $qinput[] = $request->period_id);
			
			$query_unassign .= " GROUP  BY e.emp_code,
									  p.appraisal_year,
									  p.appraisal_frequency_id, er.appraisal_type_id) assigned
					WHERE  assigned_total >= period_total  ) union all ";
			
			$query_unassign .= "
				select distinct er.emp_result_id, er.status, er.emp_code, e.emp_name, e.department_name, e.section_name, t.appraisal_type_name, e.position_name, t.appraisal_type_id, p.period_id, concat(p.appraisal_period_desc,' Start Date: ',p.start_date,' End Date: ',p.end_date) appraisal_period_desc, e.is_coporate_kpi
				From emp_result er, employee e, appraisal_type t, appraisal_item_result ir, appraisal_item I, appraisal_period p
				Where er.emp_code = e.emp_code and er.appraisal_type_id = t.appraisal_type_id
				And er.emp_result_id = ir.emp_result_id 
				and ir.appraisal_item_id = I.appraisal_item_id		
				and er.period_id = p.period_id
			";
			empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
			empty($request->department_code) ?: ($query_unassign .= " and e.department_code = ? " AND $qinput[] = $request->department_code);
			empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
			empty($request->appraisal_level_id) ?: ($query_unassign .= " And er.emp_code in (select emp_code from emp_level where appraisal_level_id = ?) " AND $qinput[] = $request->appraisal_level_id);
			empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
			empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
			empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);		
			
		} else {
			$query_unassign = "
				Select distinct null as emp_result_id,  'Unassigned' as status, emp_code, emp_name, department_name, section_name, 'N/A' as appraisal_type_name, position_name, null appraisal_type_id, 0 period_id, 'Unassigned' appraisal_period_desc, is_coporate_kpi
				From employee
				Where is_active = 1
				and chief_emp_code = ?
			";
			$qinput[] = Auth::id();
			empty($request->position_code) ?: ($query_unassign .= " and position_code = ? " AND $qinput[] = $request->position_code);
			empty($request->department_code) ?: ($query_unassign .= " and department_code = ? " AND $qinput[] = $request->department_code);
			empty($request->emp_code) ?: ($query_unassign .= " and emp_code = ? " AND $qinput[] = $request->emp_code);
			empty($request->appraisal_level_id) ?: ($query_unassign .= " And emp_code in (select emp_code from emp_level where appraisal_level_id = ?) " AND $qinput[] = $request->appraisal_level_id);			
			
			$query_unassign .= "
				and emp_code not in 
				(SELECT emp_code
					FROM   (SELECT e.emp_code,
								   p.appraisal_year,
								   p.appraisal_frequency_id,
								   er.appraisal_type_id,
								   Count(1) assigned_total,
								   z.period_total
							FROM   emp_result er,
								   employee e,
								   appraisal_period p,
								   (SELECT appraisal_year,
										   appraisal_frequency_id,
										   Count(1) period_total
									FROM   appraisal_period
			";
			empty($request->period_id) ?: ($query_unassign .= " where period_id = ? " AND $qinput[] = $request->period_id);
			$query_unassign .= "
									GROUP  BY appraisal_year,
											  appraisal_frequency_id) z
							WHERE  er.emp_code = e.emp_code
								   AND er.period_id = p.period_id
								   AND p.appraisal_year = z.appraisal_year
								   AND p.appraisal_frequency_id = z.appraisal_frequency_id
								   AND e.chief_emp_code = ?
			";
			$qinput[] = Auth::id();
			//empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
			//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
			//empty($request->appraisal_level_id) ?: ($query_unassign .= " and I.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
			empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
			//empty($request->period_id) ?: ($query_unassign .= " and er.period_id = ? " AND $qinput[] = $request->period_id);
			empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
			empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);			
			
			$query_unassign .= " GROUP  BY e.emp_code,
									  p.appraisal_year,
									  p.appraisal_frequency_id,er.appraisal_type_id) assigned
					WHERE  assigned_total = period_total ) union all ";
			
			$query_unassign .= "
				select distinct er.emp_result_id, er.status, er.emp_code, e.emp_name, e.department_name, e.section_name, t.appraisal_type_name, e.position_name, t.appraisal_type_id, p.period_id, concat(p.appraisal_period_desc,' Start Date: ',p.start_date,' End Date: ',p.end_date) appraisal_period_desc, e.is_coporate_kpi
				From emp_result er, employee e, appraisal_type t, appraisal_item_result ir, appraisal_item I, appraisal_period p
				Where er.emp_code = e.emp_code and er.appraisal_type_id = t.appraisal_type_id
				And er.emp_result_id = ir.emp_result_id 
				and ir.appraisal_item_id = I.appraisal_item_id	
				and er.period_id = p.period_id
				and e.chief_emp_code = ? 
			";
			$qinput[] = Auth::id();
			
			empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
			empty($request->department_code) ?: ($query_unassign .= " and e.department_code = ? " AND $qinput[] = $request->department_code);
			empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
			empty($request->appraisal_level_id) ?: ($query_unassign .= " and I.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
			empty($request->appraisal_type_id) ?: ($query_unassign .= " and er.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);	
			//empty($request->period_id) ?: ($query_unassign .= " and er.period_id = ? " AND $qinput[] = $request->period_id);	
			empty($request->appraisal_year) ?: ($query_unassign .= " and p.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			empty($request->frequency_id) ?: ($query_unassign .= " and p.appraisal_frequency_id = ? " AND $qinput[] = $request->frequency_id);
			empty($request->period_id) ?: ($query_unassign .= " and p.period_id = ? " AND $qinput[] = $request->period_id);			
		}	
		
		$items = DB::select($query_unassign, $qinput);
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);			

		$groups = array();
		foreach ($itemsForCurrentPage as $item) {
			$key = "p".$item->period_id;
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'items' => array($item),
					'appraisal_period_desc' => $item->appraisal_period_desc,
					'count' => 1,
				);
			} else {
				$groups[$key]['items'][] = $item;
				$groups[$key]['count'] += 1;
			}
		}		
		$resultT = $result->toArray();
		$resultT['group'] = $groups;
		return response()->json($resultT);			

	}
	
	public function assign_template(Request $request)
	{	
		$qinput = array();
		$query = "
			select a.appraisal_item_id, a.appraisal_item_name, a.structure_id, b.structure_name, b.nof_target_score, f.form_id, f.form_name, f.app_url, c.weight_percent, a.max_value, a.unit_deduct_score
			from appraisal_item a
			left outer join appraisal_structure b
			on a.structure_id = b.structure_id
			left outer join form_type f
			on b.form_id = f.form_id
			left outer join appraisal_criteria c
			on b.structure_id = c.structure_id
			where a.is_active = 1
		";
		
		empty($request->appraisal_level_id) ?: ($query .= " and c.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);	
		empty($request->appraisal_level_id) ?: ($query .= " and a.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);	
		empty($request->department_code) ?: ($query .= " and a.department_code = ? " AND $qinput[] = $request->department_code);
		
		$qfooter = " order by b.seq_no, a.appraisal_item_name ";
		
		$items = DB::select($query . $qfooter, $qinput);
		
		$groups = array();
		foreach ($items as $item) {
			$key = $item->structure_name;
			if (!isset($groups[$key])) {
				if ($item->form_name == 'Quantity') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Structure',
							'column_name' => 'structure_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'Perspective',
							'column_name' => 'perspective_name',
							'data_type' => 'text',
						],						
						[
							'column_display' => 'UOM',
							'column_name' => 'uom_name',
							'data_type' => 'text',
						],					
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],						
					];
				} elseif ($item->form_name == 'Quality') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],				
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				} elseif ($item->form_name == 'Deduct Score') {
					$columns = [
						[
							'column_display' => 'Appraisal Item Name',
							'column_name' => 'appraisal_item_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Appraisal Level',
							'column_name' => 'appraisal_level_name',
							'data_type' => 'text',
						],
						[
							'column_display' => 'Max Value',
							'column_name' => 'max_value',
							'data_type' => 'number',
						],						
						[
							'column_display' => 'Deduct Score/Unit',
							'column_name' => 'unit_deduct_score',
							'data_type' => 'number',
						],									
						[
							'column_display' => 'IsActive',
							'column_name' => 'is_active',
							'data_type' => 'checkbox',
						],									
					];
				}
				$groups[$key] = array(
					'items' => array($item),
					'count' => 1,
					'columns' => $columns,
					'structure_id' => $item->structure_id,
					'form_id' => $item->form_id,
					'form_url' => $item->app_url,
					'nof_target_score' => $item->nof_target_score,
					'total_weight' => $item->weight_percent
				);
			} else {
				$groups[$key]['items'][] = $item;
				$groups[$key]['count'] += 1;
			}
		}		
	//	$resultT = $items->toArray();
	//	$items['group'] = $groups;
		return response()->json(['data' => $items, 'group' => $groups]);	

	}	
	
	public function store(Request $request)
	{
		$errors = array();
		$semp_code = array();
		
		
		// hr cannot assign
		$al = DB::select("
			select b.appraisal_level_id, b.is_hr
			from emp_level a
			left outer join appraisal_level b
			on a.appraisal_level_id = b.appraisal_level_id
			where a.emp_code = ?
		", array(Auth::id()));
		
		if (empty($al)) {
			$is_hr = null;
			$al_id = null;
		} else {
			$is_hr = $al[0]->is_hr;
			$al_id = $al[0]->appraisal_level_id;
		}			
		if (empty($is_hr)) {
			return response()->json(['status' => 400, 'data' => ['Invalid action for HR.']]);
		}
		
		
		if ($request->head_params['action_to'] > 16) {
			return response()->json(['status' => 400, 'data' => ['Invalid action.']]);
			// if ($request->head_params['action_to'] == 17 || $request->head_params['action_to'] == 25 || $request->head_params['action_to'] == 29) {
			// } else {
				// return response()->json(['status' => 400, 'data' => ['Invalid action.']]);
			// }
		}
		
		$validator = Validator::make($request->head_params, [
			'appraisal_type_id' => 'required',
			'appraisal_year' => 'required',
			'frequency_id' => 'required',			
			'action_to' => 'required'
		]);

		if ($validator->fails()) {
			$errors[] = ['appraisal_item_id' => '', 'appraisal_item_name' => '', 'data' => $validator->errors()];
		}			
		
		$frequency = AppraisalFrequency::find($request->head_params['frequency_id']);
		
		if (empty($frequency)) {
			return response()->json(['status' => 400, 'data' => ['Frequency not found.']]);
		}
		
		//$period_count = 12 / $frequency->frequency_month_value;
		
		$period_errors = array();
		
		if (empty($request->head_params['period_id'])) {
			// foreach (range(1,$period_count,1) as $p) {
				// $appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_no',$p)->where('appraisal_frequency_id',$request->head_params['frequency_id']);
				// if ($appraisal_period->count() == 0) {
					// $period_errors[] = 'Appraisal Period not found for Appraisal Year: ' . $request->head_params['appraisal_year'] . ' Period Number: ' . $p . ' Appraisal Frequency ID: ' . $request->head_params['frequency_id'];
				// }			
			// }
			
			// if (!empty($period_errors)) {
				// return response()->json(['status' => 400, 'data' => $period_errors]);			
			//}			
			
			$period_check = DB::select("
				select period_id
				from appraisal_period
				where appraisal_year = ?
				and appraisal_frequency_id = ?
			", array($request->head_params['appraisal_year'], $request->head_params['frequency_id']));
			
			if (empty($period_check)) {
				return response()->json(['status' => 400, 'data' => ['Appraisal Period not found for Appraisal Year: ' . $request->head_params['appraisal_year'] . ' Appraisal Frequency ID: ' . $request->head_params['frequency_id']]]);
			}
			

			
		} else {
			$appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_id',$request->head_params['period_id'])->where('appraisal_frequency_id',$request->head_params['frequency_id']);
			if ($appraisal_period->count() == 0) {
				$period_errors[] = 'Appraisal Period not found for Appraisal Year: ' . $request->head_params['appraisal_year'] . ' Period ID: ' . $request->head_params['period_id'] . ' Appraisal Frequency ID: ' . $request->head_params['frequency_id'];
				return response()->json(['status' => 400, 'data' => $period_errors]);
			}
			
		}
		

		
		foreach ($request->appraisal_items as $i) {
			if (array_key_exists ( 'form_id' , $i ) == false) {
				$i['form_id'] = 0;
			}
			
			if ($i['form_id'] == 1) {
				if (array_key_exists ( 'nof_target_score' , $i ) == false) {
					$i['nof_target_score'] = 0;
				}				
				if ($i['nof_target_score'] == 1) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						'weight_percent' => 'required|numeric',
					]);
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 2) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',						
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 3) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',	
						// 'score3_target_start' => 'required|numeric',
						// 'score3_target_end' => 'required|numeric',							
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 4) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',	
						// 'score3_target_start' => 'required|numeric',
						// 'score3_target_end' => 'required|numeric',	
						// 'score4_target_start' => 'required|numeric',
						// 'score4_target_end' => 'required|numeric',							
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 5) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',	
						// 'score3_target_start' => 'required|numeric',
						// 'score3_target_end' => 'required|numeric',	
						// 'score4_target_start' => 'required|numeric',
						// 'score4_target_end' => 'required|numeric',	
						// 'score5_target_start' => 'required|numeric',
						// 'score5_target_end' => 'required|numeric',								
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
				} else {
					$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => 'Invalid Number of Target Score.'];				
				}

			} elseif ($i['form_id'] == 2) {
			
				$validator = Validator::make($i, [
					'appraisal_item_id' => 'required|integer',
					'target_value' => 'required|numeric',
					'weight_percent' => 'required|numeric',
				]);

				if ($validator->fails()) {
					$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
				}			
			
			} elseif ($i['form_id'] == 3) {
			
				$validator = Validator::make($i, [
					'appraisal_item_id' => 'required|integer',
					'max_value' => 'required|numeric',
					'deduct_score_unit' => 'required|numeric',
				]);

				if ($validator->fails()) {
					$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
				}				
			
			} else {
				$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => 'Invalid Form.'];
			}
		}
		
		if (count($errors) > 0) {
			return response()->json(['status' => 400, 'data' => $errors]);
		}
		
		if (empty($request->employees)) {
			return response()->json(['status' => 200, 'data' => []]);
		}
		
		$already_assigned = array();
		
		foreach ($request->employees as $e) {
			// $check_unassign = DB::select("
				// select emp_code
				// from emp_result
				// where emp_code = ?
			// ", array($e['emp_code']));
			


			if (empty($request->head_params['period_id'])) {
				foreach ($period_check as $p) {
					// $appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_no',$p)->where('appraisal_frequency_id',$request->head_params['frequency_id']);
					$period_id = $p->period_id;
					$qinput = array();
					$query_unassign = "
						 select emp_code 
						 from emp_result
						 where emp_code = ?
						 and period_id = ?
					";
					$qinput[] = $e['emp_code'];
					$qinput[] = $period_id;
				//	empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
				//	empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				//	empty($request->appraisal_level_id) ?: ($query_unassign .= " and I.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
					empty($request->head_params['appraisal_type_id']) ?: ($query_unassign .= " and appraisal_type_id = ? " AND $qinput[] = $request->head_params['appraisal_type_id']);	
					
					$check_unassign = DB::select($query_unassign,$qinput);	

					if (empty($check_unassign)) {
						$stage = WorkflowStage::find($request->head_params['action_to']);
						$employee = Employee::find($e['emp_code']);
						empty($employee) ? $chief_emp_code = null : $chief_emp_code = $employee->chief_emp_code;
						$emp_result = new EmpResult;
						$emp_result->appraisal_type_id = $request->head_params['appraisal_type_id'];
						$emp_result->period_id = $period_id;
						$emp_result->emp_code = $e['emp_code'];
						$emp_result->chief_emp_code = $chief_emp_code;
						$emp_result->result_score = 0;
						$emp_result->b_rate = 0;
						$emp_result->b_amount = 0;
						$emp_result->grade = null;
						$emp_result->raise_amount = 0;
						$emp_result->new_s_amount = 0;
						$emp_result->status = $stage->status;
						$emp_result->stage_id = $stage->stage_id;
						$emp_result->created_by = Auth::id();
						$emp_result->updated_by = Auth::id();
						$emp_result->save();
						
						$emp_stage = new EmpResultStage;
						$emp_stage->emp_result_id = $emp_result->emp_result_id;
						$emp_stage->stage_id = $stage->stage_id;
						$emp_stage->remark = null;
						$emp_stage->created_by = Auth::id();
						$emp_stage->updated_by = Auth::id();
						$emp_stage->save();
						
						$semp_code[] = ['emp_code' => $e['emp_code'], 'period_id' => $period_id];
						
						foreach ($request->appraisal_items as $i) {
							if ($i['form_id'] == 1) {		
								if ($i['nof_target_score'] == 1) {

									$aitem = new AppraisalItemResult;
									$aitem->emp_result_id = $emp_result->emp_result_id;
									$aitem->period_id = $period_id;
									$aitem->emp_code = $e['emp_code'];
									$aitem->appraisal_item_id = $i['appraisal_item_id'];
									$aitem->target_value = $i['target_value'];
									$aitem->weight_percent = $i['weight_percent'];
									$aitem->score1_target_start = $i['score1_target_start'];
									$aitem->score1_target_end = $i['score1_target_end'];
									$aitem->over_value = 0;
									$aitem->weigh_score = 0;
									$aitem->created_by = Auth::id();
									$aitem->updated_by = Auth::id();
									$aitem->save();					
									
								} elseif ($i['nof_target_score'] == 2) {

									$aitem = new AppraisalItemResult;
									$aitem->emp_result_id = $emp_result->emp_result_id;
									$aitem->period_id = $period_id;
									$aitem->emp_code = $e['emp_code'];
									$aitem->appraisal_item_id = $i['appraisal_item_id'];
									$aitem->target_value = $i['target_value'];
									$aitem->weight_percent = $i['weight_percent'];
									$aitem->score1_target_start = $i['score1_target_start'];
									$aitem->score1_target_end = $i['score1_target_end'];
									$aitem->score2_target_start = $i['score2_target_start'];
									$aitem->score2_target_end = $i['score2_target_end'];						
									$aitem->over_value = 0;
									$aitem->weigh_score = 0;
									$aitem->created_by = Auth::id();
									$aitem->updated_by = Auth::id();
									$aitem->save();							
									
								} elseif ($i['nof_target_score'] == 3) {

									$aitem = new AppraisalItemResult;
									$aitem->emp_result_id = $emp_result->emp_result_id;
									$aitem->period_id = $period_id;
									$aitem->emp_code = $e['emp_code'];
									$aitem->appraisal_item_id = $i['appraisal_item_id'];
									$aitem->target_value = $i['target_value'];
									$aitem->weight_percent = $i['weight_percent'];
									$aitem->score1_target_start = $i['score1_target_start'];
									$aitem->score1_target_end = $i['score1_target_end'];
									$aitem->score2_target_start = $i['score2_target_start'];
									$aitem->score2_target_end = $i['score2_target_end'];	
									$aitem->score3_target_start = $i['score3_target_start'];
									$aitem->score3_target_end = $i['score3_target_end'];							
									$aitem->over_value = 0;
									$aitem->weigh_score = 0;
									$aitem->created_by = Auth::id();
									$aitem->updated_by = Auth::id();
									$aitem->save();							
									
								} elseif ($i['nof_target_score'] == 4) {
								
									$aitem = new AppraisalItemResult;
									$aitem->emp_result_id = $emp_result->emp_result_id;
									$aitem->period_id = $period_id;
									$aitem->emp_code = $e['emp_code'];
									$aitem->appraisal_item_id = $i['appraisal_item_id'];
									$aitem->target_value = $i['target_value'];
									$aitem->weight_percent = $i['weight_percent'];
									$aitem->score1_target_start = $i['score1_target_start'];
									$aitem->score1_target_end = $i['score1_target_end'];
									$aitem->score2_target_start = $i['score2_target_start'];
									$aitem->score2_target_end = $i['score2_target_end'];	
									$aitem->score3_target_start = $i['score3_target_start'];
									$aitem->score3_target_end = $i['score3_target_end'];
									$aitem->score4_target_start = $i['score4_target_start'];
									$aitem->score4_target_end = $i['score4_target_end'];							
									$aitem->over_value = 0;
									$aitem->weigh_score = 0;
									$aitem->created_by = Auth::id();
									$aitem->updated_by = Auth::id();
									$aitem->save();						
									
								} elseif ($i['nof_target_score'] == 5) {
								
									$aitem = new AppraisalItemResult;
									$aitem->emp_result_id = $emp_result->emp_result_id;
									$aitem->period_id = $period_id;
									$aitem->emp_code = $e['emp_code'];
									$aitem->appraisal_item_id = $i['appraisal_item_id'];
									$aitem->target_value = $i['target_value'];
									$aitem->weight_percent = $i['weight_percent'];
									$aitem->score1_target_start = $i['score1_target_start'];
									$aitem->score1_target_end = $i['score1_target_end'];
									$aitem->score2_target_start = $i['score2_target_start'];
									$aitem->score2_target_end = $i['score2_target_end'];	
									$aitem->score3_target_start = $i['score3_target_start'];
									$aitem->score3_target_end = $i['score3_target_end'];
									$aitem->score4_target_start = $i['score4_target_start'];
									$aitem->score4_target_end = $i['score4_target_end'];		
									$aitem->score5_target_start = $i['score5_target_start'];
									$aitem->score5_target_end = $i['score5_target_end'];							
									$aitem->over_value = 0;
									$aitem->weigh_score = 0;
									$aitem->created_by = Auth::id();
									$aitem->updated_by = Auth::id();
									$aitem->save();									
								}

							} elseif ($i['form_id'] == 2) {

								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_code = $e['emp_code'];
								$aitem->appraisal_item_id = $i['appraisal_item_id'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();
								
							} elseif ($i['form_id'] == 3) {
						
								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_code = $e['emp_code'];
								$aitem->appraisal_item_id = $i['appraisal_item_id'];
								$aitem->max_value = $i['max_value'];
								$aitem->deduct_score_unit = $i['deduct_score_unit'];
								$aitem->weight_percent = 0;
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();
							
							} 	
						}						
					} else {
						$already_assigned = ['emp_code' => $e['emp_code'], 'period_id' => $period_id];
					}					
				} 
			} else {
				$appraisal_period = AppraisalPeriod::where('appraisal_year',$request->head_params['appraisal_year'])->where('period_id',$request->head_params['period_id'])->where('appraisal_frequency_id',$request->head_params['frequency_id']);	
				$period_id = $appraisal_period->first()->period_id;
				$qinput = array();
				$query_unassign = "
					 select emp_code 
					 from emp_result
					 Where emp_code = ?
					 and period_id = ?
				";
				$qinput[] = $e['emp_code'];
				$qinput[] = $period_id;
				//empty($request->position_code) ?: ($query_unassign .= " and e.position_code = ? " AND $qinput[] = $request->position_code);
				//empty($request->emp_code) ?: ($query_unassign .= " and e.emp_code = ? " AND $qinput[] = $request->emp_code);	
				//empty($request->appraisal_level_id) ?: ($query_unassign .= " and I.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
				empty($request->head_params['appraisal_type_id']) ?: ($query_unassign .= " and appraisal_type_id = ? " AND $qinput[] =  $request->head_params['appraisal_type_id']);	
				
				$check_unassign = DB::select($query_unassign,$qinput);		
				if (empty($check_unassign)) {
					$stage = WorkflowStage::find($request->head_params['action_to']);
					$employee = Employee::find($e['emp_code']);
					empty($employee) ? $chief_emp_code = null : $chief_emp_code = $employee->chief_emp_code;
					$emp_result = new EmpResult;
					$emp_result->appraisal_type_id = $request->head_params['appraisal_type_id'];
					$emp_result->period_id = $period_id;
					$emp_result->emp_code = $e['emp_code'];
					$emp_result->chief_emp_code = $chief_emp_code;
					$emp_result->result_score = 0;
					$emp_result->b_rate = 0;
					$emp_result->b_amount = 0;
					$emp_result->grade = null;
					$emp_result->raise_amount = 0;
					$emp_result->new_s_amount = 0;
					$emp_result->status = $stage->status;
					$emp_result->stage_id = $stage->stage_id;
					$emp_result->created_by = Auth::id();
					$emp_result->updated_by = Auth::id();
					$emp_result->save();
					
					$emp_stage = new EmpResultStage;
					$emp_stage->emp_result_id = $emp_result->emp_result_id;
					$emp_stage->stage_id = $stage->stage_id;
					$emp_stage->remark = null;
					$emp_stage->created_by = Auth::id();
					$emp_stage->updated_by = Auth::id();
					$emp_stage->save();
					
					$semp_code[] = ['emp_code' => $e['emp_code'], 'period_id' => $period_id];
					
					foreach ($request->appraisal_items as $i) {
						if ($i['form_id'] == 1) {		
							if ($i['nof_target_score'] == 1) {

								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_code = $e['emp_code'];
								$aitem->appraisal_item_id = $i['appraisal_item_id'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								$aitem->score1_target_start = $i['score1_target_start'];
								$aitem->score1_target_end = $i['score1_target_end'];
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();					
								
							} elseif ($i['nof_target_score'] == 2) {

								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_code = $e['emp_code'];
								$aitem->appraisal_item_id = $i['appraisal_item_id'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								$aitem->score1_target_start = $i['score1_target_start'];
								$aitem->score1_target_end = $i['score1_target_end'];
								$aitem->score2_target_start = $i['score2_target_start'];
								$aitem->score2_target_end = $i['score2_target_end'];						
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();							
								
							} elseif ($i['nof_target_score'] == 3) {

								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_code = $e['emp_code'];
								$aitem->appraisal_item_id = $i['appraisal_item_id'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								$aitem->score1_target_start = $i['score1_target_start'];
								$aitem->score1_target_end = $i['score1_target_end'];
								$aitem->score2_target_start = $i['score2_target_start'];
								$aitem->score2_target_end = $i['score2_target_end'];	
								$aitem->score3_target_start = $i['score3_target_start'];
								$aitem->score3_target_end = $i['score3_target_end'];							
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();							
								
							} elseif ($i['nof_target_score'] == 4) {
							
								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_code = $e['emp_code'];
								$aitem->appraisal_item_id = $i['appraisal_item_id'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								$aitem->score1_target_start = $i['score1_target_start'];
								$aitem->score1_target_end = $i['score1_target_end'];
								$aitem->score2_target_start = $i['score2_target_start'];
								$aitem->score2_target_end = $i['score2_target_end'];	
								$aitem->score3_target_start = $i['score3_target_start'];
								$aitem->score3_target_end = $i['score3_target_end'];
								$aitem->score4_target_start = $i['score4_target_start'];
								$aitem->score4_target_end = $i['score4_target_end'];							
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();						
								
							} elseif ($i['nof_target_score'] == 5) {
							
								$aitem = new AppraisalItemResult;
								$aitem->emp_result_id = $emp_result->emp_result_id;
								$aitem->period_id = $period_id;
								$aitem->emp_code = $e['emp_code'];
								$aitem->appraisal_item_id = $i['appraisal_item_id'];
								$aitem->target_value = $i['target_value'];
								$aitem->weight_percent = $i['weight_percent'];
								$aitem->score1_target_start = $i['score1_target_start'];
								$aitem->score1_target_end = $i['score1_target_end'];
								$aitem->score2_target_start = $i['score2_target_start'];
								$aitem->score2_target_end = $i['score2_target_end'];	
								$aitem->score3_target_start = $i['score3_target_start'];
								$aitem->score3_target_end = $i['score3_target_end'];
								$aitem->score4_target_start = $i['score4_target_start'];
								$aitem->score4_target_end = $i['score4_target_end'];		
								$aitem->score5_target_start = $i['score5_target_start'];
								$aitem->score5_target_end = $i['score5_target_end'];							
								$aitem->over_value = 0;
								$aitem->weigh_score = 0;
								$aitem->created_by = Auth::id();
								$aitem->updated_by = Auth::id();
								$aitem->save();									
							}

						} elseif ($i['form_id'] == 2) {

							$aitem = new AppraisalItemResult;
							$aitem->emp_result_id = $emp_result->emp_result_id;
							$aitem->period_id = $period_id;
							$aitem->emp_code = $e['emp_code'];
							$aitem->appraisal_item_id = $i['appraisal_item_id'];
							$aitem->target_value = $i['target_value'];
							$aitem->weight_percent = $i['weight_percent'];
							$aitem->over_value = 0;
							$aitem->weigh_score = 0;
							$aitem->created_by = Auth::id();
							$aitem->updated_by = Auth::id();
							$aitem->save();
							
						} elseif ($i['form_id'] == 3) {
					
							$aitem = new AppraisalItemResult;
							$aitem->emp_result_id = $emp_result->emp_result_id;
							$aitem->period_id = $period_id;
							$aitem->emp_code = $e['emp_code'];
							$aitem->appraisal_item_id = $i['appraisal_item_id'];
							$aitem->max_value = $i['max_value'];
							$aitem->deduct_score_unit = $i['deduct_score_unit'];
							$aitem->weight_percent = 0;
							$aitem->over_value = 0;
							$aitem->weigh_score = 0;
							$aitem->created_by = Auth::id();
							$aitem->updated_by = Auth::id();
							$aitem->save();
						
						} 	
					}	
				} else {
					$already_assigned[] = ['emp_code' => $e['emp_code'], 'period_id' => $period_id];
				}
			
			}
		}
		
		return response()->json(['status' => 200, 'data' => $semp_code, 'already_assigned' => $already_assigned]);
	}	

	public function show(Request $request, $emp_result_id)
	{
		$head = DB::select("
			SELECT b.emp_code, b.emp_name, b.working_start_date, b.position_name, b.section_name, b.department_name, b.chief_emp_code, e.emp_name chief_emp_name, c.appraisal_period_desc, d.appraisal_type_name, a.stage_id, f.status, f.edit_flag, b.is_coporate_kpi
			FROM emp_result a
			left outer join employee b
			on a.emp_code = b.emp_code
			left outer join appraisal_period c
			on c.period_id = a.period_id
			left outer join appraisal_type d
			on a.appraisal_type_id = d.appraisal_type_id
			left outer join employee e
			on b.chief_emp_code = e.emp_code
			left outer join workflow_stage f
			on a.stage_id = f.stage_id
			where a.emp_result_id = ?
		", array($emp_result_id));
		
		$items = DB::select("
			select b.appraisal_item_name, b.structure_id, a.*
			from appraisal_item_result a
			left outer join appraisal_item b
			on a.appraisal_item_id = b.appraisal_item_id		
			where a.emp_result_id = ?
		", array($emp_result_id));
		
		return response()->json(['head' => $head, 'data' => $items]);		
	}
	
	public function update(Request $request, $emp_result_id)
	{
		$errors = array();
		
		if ($request->head_params['action_to'] > 16) {
			if ($request->head_params['action_to'] == 17 || $request->head_params['action_to'] == 25 || $request->head_params['action_to'] == 29) {
			} else {
				return response()->json(['status' => 400, 'data' => 'Invalid action.']);
			}
		}
		
		$validator = Validator::make($request->head_params, [
			'appraisal_type_id' => 'required',
			'period_id' => 'required',		
			'action_to' => 'required'
		]);
		
		if ($validator->fails()) {
			$errors[] = ['appraisal_item_id' => '', 'appraisal_item_name' => '', 'data' => $validator->errors()];
		}			
		
		foreach ($request->appraisal_items as $i) {
			if (array_key_exists ( 'form_id' , $i ) == false) {
				$i['form_id'] = 0;
			}
			
			if ($i['form_id'] == 1) {
				if (array_key_exists ( 'nof_target_score' , $i ) == false) {
					$i['nof_target_score'] = 0;
				}				
				if ($i['nof_target_score'] == 1) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						'weight_percent' => 'required|numeric',
					]);
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 2) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',						
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 3) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',	
						// 'score3_target_start' => 'required|numeric',
						// 'score3_target_end' => 'required|numeric',							
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 4) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',	
						// 'score3_target_start' => 'required|numeric',
						// 'score3_target_end' => 'required|numeric',	
						// 'score4_target_start' => 'required|numeric',
						// 'score4_target_end' => 'required|numeric',							
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
					
				} elseif ($i['nof_target_score'] == 5) {
					$validator = Validator::make($i, [
						'appraisal_item_id' => 'required|integer',
						'target_value' => 'required|numeric',
						// 'score1_target_start' => 'required|numeric',
						// 'score1_target_end' => 'required|numeric',
						// 'score2_target_start' => 'required|numeric',
						// 'score2_target_end' => 'required|numeric',	
						// 'score3_target_start' => 'required|numeric',
						// 'score3_target_end' => 'required|numeric',	
						// 'score4_target_start' => 'required|numeric',
						// 'score4_target_end' => 'required|numeric',	
						// 'score5_target_start' => 'required|numeric',
						// 'score5_target_end' => 'required|numeric',								
						'weight_percent' => 'required|numeric',
					]);			
					if ($validator->fails()) {
						$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
					}						
				} else {
					$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => 'Invalid Number of Target Score.'];				
				}

			} elseif ($i['form_id'] == 2) {
			
				$validator = Validator::make($i, [
					'appraisal_item_id' => 'required|integer',
					'target_value' => 'required|numeric',
					'weight_percent' => 'required|numeric',
				]);

				if ($validator->fails()) {
					$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
				}			
			
			} elseif ($i['form_id'] == 3) {
			
				$validator = Validator::make($i, [
					'appraisal_item_id' => 'required|integer',
					'max_value' => 'required|numeric',
					'deduct_score_unit' => 'required|numeric',
				]);

				if ($validator->fails()) {
					$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => $validator->errors()];
				}				
			
			} else {
				$errors[] = ['appraisal_item_id' => $i['appraisal_item_id'], 'appraisal_item_name' => $i['appraisal_item_name'], 'data' => 'Invalid Form.'];
			}
		}
		
		if (count($errors) > 0) {
			return response()->json(['status' => 400, 'data' => $errors]);
		}
		
		$stage = WorkflowStage::find($request->head_params['action_to']);
		$emp_result = EmpResult::find($emp_result_id);
		$emp_result->status = $stage->status;
		$emp_result->stage_id = $stage->stage_id;
		$emp_result->updated_by = Auth::id();
		$emp_result->save();
		
		$emp_stage = new EmpResultStage;
		$emp_stage->emp_result_id = $emp_result->emp_result_id;
		$emp_stage->stage_id = $stage->stage_id;
		$emp_stage->remark = null;
		$emp_stage->created_by = Auth::id();
		$emp_stage->updated_by = Auth::id();
		$emp_stage->save();
		
		foreach ($request->appraisal_items as $i) {
			if ($i['form_id'] == 1) {		
				if ($i['nof_target_score'] == 1) {

					$aitem = AppraisalItemResult::find($i['appraisal_item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_code = $emp_result->emp_code;
					$aitem->appraisal_item_id = $i['appraisal_item_id'];
					$aitem->target_value = $i['target_value'];
					$aitem->weight_percent = $i['weight_percent'];
					$aitem->score1_target_start = $i['score1_target_start'];
					$aitem->score1_target_end = $i['score1_target_end'];
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->updated_by = Auth::id();
					$aitem->save();					
					
				} elseif ($i['nof_target_score'] == 2) {

					$aitem = AppraisalItemResult::find($i['appraisal_item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_code = $emp_result->emp_code;
					$aitem->appraisal_item_id = $i['appraisal_item_id'];
					$aitem->target_value = $i['target_value'];
					$aitem->weight_percent = $i['weight_percent'];
					$aitem->score1_target_start = $i['score1_target_start'];
					$aitem->score1_target_end = $i['score1_target_end'];
					$aitem->score2_target_start = $i['score2_target_start'];
					$aitem->score2_target_end = $i['score2_target_end'];						
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->updated_by = Auth::id();
					$aitem->save();							
					
				} elseif ($i['nof_target_score'] == 3) {

					$aitem = AppraisalItemResult::find($i['appraisal_item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_code = $emp_result->emp_code;
					$aitem->appraisal_item_id = $i['appraisal_item_id'];
					$aitem->target_value = $i['target_value'];
					$aitem->weight_percent = $i['weight_percent'];
					$aitem->score1_target_start = $i['score1_target_start'];
					$aitem->score1_target_end = $i['score1_target_end'];
					$aitem->score2_target_start = $i['score2_target_start'];
					$aitem->score2_target_end = $i['score2_target_end'];	
					$aitem->score3_target_start = $i['score3_target_start'];
					$aitem->score3_target_end = $i['score3_target_end'];							
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->updated_by = Auth::id();
					$aitem->save();							
					
				} elseif ($i['nof_target_score'] == 4) {
				
					$aitem = AppraisalItemResult::find($i['appraisal_item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_code = $emp_result->emp_code;
					$aitem->appraisal_item_id = $i['appraisal_item_id'];
					$aitem->target_value = $i['target_value'];
					$aitem->weight_percent = $i['weight_percent'];
					$aitem->score1_target_start = $i['score1_target_start'];
					$aitem->score1_target_end = $i['score1_target_end'];
					$aitem->score2_target_start = $i['score2_target_start'];
					$aitem->score2_target_end = $i['score2_target_end'];	
					$aitem->score3_target_start = $i['score3_target_start'];
					$aitem->score3_target_end = $i['score3_target_end'];
					$aitem->score4_target_start = $i['score4_target_start'];
					$aitem->score4_target_end = $i['score4_target_end'];							
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->updated_by = Auth::id();
					$aitem->save();						
					
				} elseif ($i['nof_target_score'] == 5) {
				
					$aitem = AppraisalItemResult::find($i['appraisal_item_result_id']);
					if (empty($aitem)) {
						$aitem = new AppraisalItemResult;
						$aitem->created_by = Auth::id();
					}
					$aitem->emp_result_id = $emp_result->emp_result_id;
					$aitem->period_id = $request->head_params['period_id'];
					$aitem->emp_code = $emp_result->emp_code;
					$aitem->appraisal_item_id = $i['appraisal_item_id'];
					$aitem->target_value = $i['target_value'];
					$aitem->weight_percent = $i['weight_percent'];
					$aitem->score1_target_start = $i['score1_target_start'];
					$aitem->score1_target_end = $i['score1_target_end'];
					$aitem->score2_target_start = $i['score2_target_start'];
					$aitem->score2_target_end = $i['score2_target_end'];	
					$aitem->score3_target_start = $i['score3_target_start'];
					$aitem->score3_target_end = $i['score3_target_end'];
					$aitem->score4_target_start = $i['score4_target_start'];
					$aitem->score4_target_end = $i['score4_target_end'];		
					$aitem->score5_target_start = $i['score5_target_start'];
					$aitem->score5_target_end = $i['score5_target_end'];							
					$aitem->over_value = 0;
					$aitem->weigh_score = 0;
					$aitem->updated_by = Auth::id();
					$aitem->save();									
				}

			} elseif ($i['form_id'] == 2) {

				$aitem = AppraisalItemResult::find($i['appraisal_item_result_id']);
				if (empty($aitem)) {
					$aitem = new AppraisalItemResult;
					$aitem->created_by = Auth::id();
				}
				$aitem->emp_result_id = $emp_result->emp_result_id;
				$aitem->period_id = $request->head_params['period_id'];
				$aitem->emp_code = $emp_result->emp_code;
				$aitem->appraisal_item_id = $i['appraisal_item_id'];
				$aitem->target_value = $i['target_value'];
				$aitem->weight_percent = $i['weight_percent'];
				$aitem->over_value = 0;
				$aitem->weigh_score = 0;
				$aitem->updated_by = Auth::id();
				$aitem->save();
				
			} elseif ($i['form_id'] == 3) {
		
				$aitem = AppraisalItemResult::find($i['appraisal_item_result_id']);
				if (empty($aitem)) {
					$aitem = new AppraisalItemResult;
					$aitem->created_by = Auth::id();
				}
				$aitem->emp_result_id = $emp_result->emp_result_id;
				$aitem->period_id = $request->head_params['period_id'];
				$aitem->emp_code = $emp_result->emp_code;
				$aitem->appraisal_item_id = $i['appraisal_item_id'];
				$aitem->max_value = $i['max_value'];
				$aitem->deduct_score_unit = $i['deduct_score_unit'];
				$aitem->weight_percent = 0;
				$aitem->over_value = 0;
				$aitem->weigh_score = 0;
				$aitem->updated_by = Auth::id();
				$aitem->save();
			
			} 	
		}		
		
		return response()->json(['status' => 200]);
		
	}
	
	public function destroy($emp_result_id)
	{
	
		try {
			$item = EmpResult::findOrFail($emp_result_id);
		} catch (ModelNotFoundException $e) {
			return response()->json(['status' => 400, 'data' => 'Appraisal Assignment not found.']);
		}	

		try {
			if ($item->status == 'Assigned' || $item->status == 'Reject' || $item->status == 'Draft') {
				EmpResultStage::where('emp_result_id',$item->emp_result_id)->delete();
				AppraisalItemResult::where('emp_result_id',$item->emp_result_id)->delete();			
				$item->delete();
			} else {
				return response()->json(['status' => 400, 'data' => 'Cannot delete Appraisal Assignment at this stage.']);
			}
		} catch (Exception $e) {
			if ($e->errorInfo[1] == 1451) {
				return response()->json(['status' => 400, 'data' => 'Cannot delete because this Appraisal Assignment is in use.']);
			} else {
				return response()->json($e->errorInfo);
			}
		}
		
		return response()->json(['status' => 200]);	
		
	}
	
}
