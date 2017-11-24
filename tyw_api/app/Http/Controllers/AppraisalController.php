<?php

namespace App\Http\Controllers;

use App\EmpResult;
use App\WorkflowStage;
use App\AppraisalItemResult;
use App\AppraisalLevel;

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

class AppraisalController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	public function year_list()
	{
		$items = DB::select("
			Select distinct appraisal_year appraisal_year_id, appraisal_year
			from appraisal_period 
			order by appraisal_year desc
		");
		return response()->json($items);
	}
	
	public function period_list(Request $request)
	{
		$items = DB::select("
			Select period_id, period_no, appraisal_period_desc
			from appraisal_period
			where appraisal_year = ?
			order by start_date asc, appraisal_period_desc asc
		", array($request->appraisal_year));
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
			
			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct emp_code
				from employee
				where chief_emp_code = ?
			", array(Auth::id()));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->emp_code;
				$re_emp[] = $e->emp_code;
			}
		
			$emp_list = array_unique($emp_list);
			
			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}					
				
			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct emp_code
					from employee
					where chief_emp_code in ({$in_emp})
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->emp_code;
					$re_emp[] = $e->emp_code;
				}			
				
				$emp_list = array_unique($emp_list);
				
				// Get array keys
				$arrayKeys = array_keys($emp_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		
			
			$re_emp[] = Auth::id();
			$re_emp = array_unique($re_emp);
			
			// Get array keys
			$arrayKeys = array_keys($re_emp);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				
			
			empty($in_emp) ? $in_emp = "null" : null;
			

			$items = DB::select("
				select distinct al.appraisal_level_id, al.appraisal_level_name
				from emp_level el, appraisal_level al
				where el.appraisal_level_id = al.appraisal_level_id
				and el.emp_code in ({$in_emp})
				order by al.appraisal_level_name
			");
		}
		
		return response()->json($items);
    }
		
	public function dep_list()
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
				Select distinct department_code, department_name
				From employee
				Order by department_name	
			");
		} else {
			// $items = DB::select("
				// Select distinct department_code, department_name
				// From employee
				// where chief_emp_code = ?
				// Order by department_name		
			// ", array(Auth::id()));
			
			// $chief_list = array();
			
			// $chief_items = DB::select("
				// select distinct emp_code
				// From employee
				// where chief_emp_code = ?	
			// ", array(Auth::id()));
			
			// foreach ($chief_items as $i) {
				// $chief_list[] = $i->emp_code;
			// }
		
			// $chief_list = array_unique($chief_list);
			
			// // Get array keys
			// $arrayKeys = array_keys($chief_list);
			// // Fetch last array key
			// $lastArrayKey = array_pop($arrayKeys);
			// //iterate array
			// $in_chief = '';
			// foreach($chief_list as $k => $v) {
				// if($k == $lastArrayKey) {
					// //during array iteration this condition states the last element.
					// $in_chief .= $v;
				// } else {
					// $in_chief .= $v . ',';
				// }
			// }					
			
			
			// do {				
				// empty($in_chief) ? $in_chief = "null" : null;
				// $ritems = DB::select("
					// Select distinct department_code, department_name
					// From employee
					// where chief_emp_code in ({$in_chief})
				// ");

				// $chief_list = array();			
				
				// foreach ($ritems as $r) {
					// $items[] = $r;
				// }
				
				// $chief_items = DB::select("
					// select distinct emp_code
					// From employee
					// where chief_emp_code in ({$in_chief})	
				// ");
				
				// foreach ($chief_items as $i) {
					// $chief_list[] = $i->emp_code;
				// }			
				
				// $chief_list = array_unique($chief_list);
				
				// // Get array keys
				// $arrayKeys = array_keys($chief_list);
				// // Fetch last array key
				// $lastArrayKey = array_pop($arrayKeys);
				// //iterate array
				// $in_chief = '';
				// foreach($chief_list as $k => $v) {
					// if($k == $lastArrayKey) {
						// //during array iteration this condition states the last element.
						// $in_chief .= $v;
					// } else {
						// $in_chief .= $v . ',';
					// }
				// }		
			// } while (!empty($chief_list));		
			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct emp_code
				from employee
				where chief_emp_code = ?
			", array(Auth::id()));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->emp_code;
				$re_emp[] = $e->emp_code;
			}
		
			$emp_list = array_unique($emp_list);
			
			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}					
				
			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct emp_code
					from employee
					where chief_emp_code in ({$in_emp})
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->emp_code;
					$re_emp[] = $e->emp_code;
				}			
				
				$emp_list = array_unique($emp_list);
				
				// Get array keys
				$arrayKeys = array_keys($emp_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		
			
			$re_emp[] = Auth::id();
			$re_emp = array_unique($re_emp);
			
			// Get array keys
			$arrayKeys = array_keys($re_emp);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				

			empty($in_emp) ? $in_emp = "null" : null;		
			$items = DB::select("
				Select distinct department_code, department_name
				From employee
				where emp_code in ({$in_emp})
				Order by department_name		
			");			
		}

		return response()->json($items);
	}
   
    public function sec_list(Request $request)
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
			$qinput = array();
			$query = "
				Select distinct section_code, section_name
				From employee
				Where 1=1
			";
			
			$qfooter = " Order by section_name ";

			empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
			
			$items = DB::select($query.$qfooter,$qinput);
		} else {
			// $query = "
				// Select distinct section_code, section_name
				// From employee
				// Where chief_emp_code = ?
			// ";		
			
			// $qinput[] = Auth::id();
				
			// empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);

			// $items = DB::select($query, $qinput);
			
			// $chief_list = array();
			// $rqinput = array();
			// $chief_input = array();
			
			// $chief_query = "
				// Select distinct emp_code
				// From employee
				// Where chief_emp_code = ?
			// ";		
			
			// $chief_input[] = Auth::id();
				
			// empty($request->department_code) ?: ($chief_query .= " and department_code = ? " AND $chief_input[] = $request->department_code);			
			
			// $chief_items = DB::select($chief_query, $chief_input);
			
			// foreach ($chief_items as $i) {
				// $chief_list[] = $i->emp_code;
			// }
			
			// $chief_list = array_unique($chief_list);
			
			// // Get array keys
			// $arrayKeys = array_keys($chief_list);
			// // Fetch last array key
			// $lastArrayKey = array_pop($arrayKeys);
			// //iterate array
			// $in_chief = '';
			// foreach($chief_list as $k => $v) {
				// if($k == $lastArrayKey) {
					// //during array iteration this condition states the last element.
					// $in_chief .= $v;
				// } else {
					// $in_chief .= $v . ',';
				// }
			// }					
			
			
			// do {				
				// empty($in_chief) ? $in_chief = "null" : null;
				// $recur_sql = "
					// Select distinct section_code, section_name
					// From employee
					// Where chief_emp_code in ({$in_chief});
				// ";		
					
				// empty($request->department_code) ?: ($recur_sql .= " and department_code = ? " AND $rqinput[] = $request->department_code);
				
				// $ritems = DB::select($recur_sql,$rqinput);
				
				// $chief_list = array();
				// $rqinput = array();				
				
				// foreach ($ritems as $r) {
					// $items[] = $r;
				// }
				
				// $chief_input = array();
				
				// $chief_query = "
					// Select distinct emp_code
					// From employee
					// Where chief_emp_code  in ({$in_chief})
				// ";		
				
				// empty($request->department_code) ?: ($chief_query .= " and department_code = ? " AND $chief_input[] = $request->department_code);		
				
				// $chief_items = DB::select($chief_query, $chief_input);
				
				// foreach ($chief_items as $i) {
					// $chief_list[] = $i->emp_code;
				// }				
				
				// $chief_list = array_unique($chief_list);
				
				// // Get array keys
				// $arrayKeys = array_keys($chief_list);
				// // Fetch last array key
				// $lastArrayKey = array_pop($arrayKeys);
				// //iterate array
				// $in_chief = '';
				// foreach($chief_list as $k => $v) {
					// if($k == $lastArrayKey) {
						// //during array iteration this condition states the last element.
						// $in_chief .= $v;
					// } else {
						// $in_chief .= $v . ',';
					// }
				// }		
			// } while (!empty($chief_list));
			
			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct emp_code
				from employee
				where chief_emp_code = ?
			", array(Auth::id()));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->emp_code;
				$re_emp[] = $e->emp_code;
			}
		
			$emp_list = array_unique($emp_list);
			
			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}					
				
			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct emp_code
					from employee
					where chief_emp_code in ({$in_emp})
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->emp_code;
					$re_emp[] = $e->emp_code;
				}			
				
				$emp_list = array_unique($emp_list);
				
				// Get array keys
				$arrayKeys = array_keys($emp_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		
			
			$re_emp[] = Auth::id();
			$re_emp = array_unique($re_emp);
			
			// Get array keys
			$arrayKeys = array_keys($re_emp);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				

			empty($in_emp) ? $in_emp = "null" : null;	
			$qinput = array();
			$query = "
				Select distinct section_code, section_name
				From employee
				Where emp_code in ({$in_emp})
			";		
			$qfooter = " order by section_name ";
			empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);

			$items = DB::select($query.$qfooter, $qinput);			
			
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
		} else {
			// $query = "
				// Select distinct position_code, position_name
				// From employee
				// Where position_name like ?
				// and chief_emp_code = ?
			// ";
			// $qinput[] = '%'.$request->position_name.'%';
			// $qinput[] = Auth::id();
			// $qfooter = " Order by position_name limit 10";
				
			// empty($request->section_code) ?: ($query .= " and section_code = ? " AND $qinput[] = $request->section_code);
			// empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);

			// $items = DB::select($query.$qfooter, $qinput);
			
			// $chief_list = array();
			// $rqinput = array();
			// $chief_input = array();
			
			// $chief_query = "
				// Select distinct emp_code
				// From employee
				// Where position_name like ?
				// and chief_emp_code = ?
			// ";		
			
			// $chief_input[] = '%'.$request->position_name.'%';
			// $chief_input[] = Auth::id();
			// $cfooter = " limit 10 ";
			
			// empty($request->section_code) ?: ($chief_query .= " and section_code = ? " AND $chief_input[] = $request->section_code);
			// empty($request->department_code) ?: ($chief_query .= " and department_code = ? " AND $chief_input[] = $request->department_code);			
				
			
			// $chief_items = DB::select($chief_query.$cfooter, $chief_input);
			
			// foreach ($chief_items as $i) {
				// $chief_list[] = $i->emp_code;
			// }
			
			// $chief_list = array_unique($chief_list);
			
			// // Get array keys
			// $arrayKeys = array_keys($chief_list);
			// // Fetch last array key
			// $lastArrayKey = array_pop($arrayKeys);
			// //iterate array
			// $in_chief = '';
			// foreach($chief_list as $k => $v) {
				// if($k == $lastArrayKey) {
					// //during array iteration this condition states the last element.
					// $in_chief .= $v;
				// } else {
					// $in_chief .= $v . ',';
				// }
			// }					
			
			
			// do {				
				// empty($in_chief) ? $in_chief = "null" : null;
							
				// $recur_sql = "
					// Select distinct position_code, position_name
					// From employee
					// Where position_name like ?
					// and chief_emp_code in ({$in_chief})
				// ";		
				// $rqinput[] = '%'.$request->position_name.'%';
				// $rfooter = ' limit 10 ';
				// empty($request->section_code) ?: ($recur_sql .= " and section_code = ? " AND $rqinput[] = $request->section_code);
				// empty($request->department_code) ?: ($recur_sql .= " and department_code = ? " AND $rqinput[] = $request->department_code);					
				
				// $ritems = DB::select($recur_sql.$rfooter,$rqinput);
				
				// $chief_list = array();
				// $rqinput = array();				
				
				// foreach ($ritems as $r) {
					// $items[] = $r;
				// }
				
				// $chief_input = array();
				
				// $chief_query = "
					// Select distinct emp_code
					// From employee
					// Where chief_emp_code  in ({$in_chief})
					// and position_name like ?
				// ";		
				
				// $chief_input[] = '%'.$request->position_name.'%';
				
				// empty($request->section_code) ?: ($chief_query .= " and section_code = ? " AND $chief_input[] = $request->section_code);
				// empty($request->department_code) ?: ($chief_query .= " and department_code = ? " AND $chief_input[] = $request->department_code);				
				// $c_footer = " limit 10 ";
				
				// $chief_items = DB::select($chief_query.$c_footer, $chief_input);
				
				// foreach ($chief_items as $i) {
					// $chief_list[] = $i->emp_code;
				// }				
				
				// $chief_list = array_unique($chief_list);
				
				// // Get array keys
				// $arrayKeys = array_keys($chief_list);
				// // Fetch last array key
				// $lastArrayKey = array_pop($arrayKeys);
				// //iterate array
				// $in_chief = '';
				// foreach($chief_list as $k => $v) {
					// if($k == $lastArrayKey) {
						// //during array iteration this condition states the last element.
						// $in_chief .= $v;
					// } else {
						// $in_chief .= $v . ',';
					// }
				// }		
			// } while (!empty($chief_list));	

			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct emp_code
				from employee
				where chief_emp_code = ?
			", array(Auth::id()));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->emp_code;
				$re_emp[] = $e->emp_code;
			}
		
			$emp_list = array_unique($emp_list);
			
			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}					
				
			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct emp_code
					from employee
					where chief_emp_code in ({$in_emp})
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->emp_code;
					$re_emp[] = $e->emp_code;
				}			
				
				$emp_list = array_unique($emp_list);
				
				// Get array keys
				$arrayKeys = array_keys($emp_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		
			
			$re_emp[] = Auth::id();
			$re_emp = array_unique($re_emp);
			
			// Get array keys
			$arrayKeys = array_keys($re_emp);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				

			empty($in_emp) ? $in_emp = "null" : null;
			
			$qinput = array();
			$query = "
				Select distinct position_code, position_name
				From employee
				Where position_name like ?
				and emp_code in ({$in_emp})
				
			";
			
			$qfooter = " Order by position_name limit 10";
			$qinput[] = '%'.$request->position_name.'%';
			empty($request->section_code) ?: ($query .= " and section_code = ? " AND $qinput[] = $request->section_code);
			empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
			
			$items = DB::select($query.$qfooter,$qinput);			
			
		}
		
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
		} else {
			// $query = "
				// Select emp_code, emp_name
				// From employee
				// Where chief_emp_code = ?
			// ";
			// $qinput[] = Auth::id();

			// $items = DB::select($query, $qinput);
			
			// $chief_list = array();
			// $rqinput = array();
				
			// foreach ($items as $i) {
				// $chief_list[] = $i->emp_code;
			// }
			
			// $chief_list = array_unique($chief_list);
			
			// // Get array keys
			// $arrayKeys = array_keys($chief_list);
			// // Fetch last array key
			// $lastArrayKey = array_pop($arrayKeys);
			// //iterate array
			// $in_chief = '';
			// foreach($chief_list as $k => $v) {
				// if($k == $lastArrayKey) {
					// //during array iteration this condition states the last element.
					// $in_chief .= $v;
				// } else {
					// $in_chief .= $v . ',';
				// }
			// }					
			
			
			// do {				
				// empty($in_chief) ? $in_chief = "null" : null;
							
				// $recur_sql = "
					// Select emp_code, emp_name
					// From employee
					// Where chief_emp_code in ({$in_chief})
				// ";							
				
				// $ritems = DB::select($recur_sql);
				
				// $chief_list = array();
				// $rqinput = array();				
				
				// foreach ($ritems as $r) {
					// $items[] = $r;
					// $chief_list[] = $r->emp_code;
				// }	
				
				// $chief_list = array_unique($chief_list);
				
				// // Get array keys
				// $arrayKeys = array_keys($chief_list);
				// // Fetch last array key
				// $lastArrayKey = array_pop($arrayKeys);
				// //iterate array
				// $in_chief = '';
				// foreach($chief_list as $k => $v) {
					// if($k == $lastArrayKey) {
						// //during array iteration this condition states the last element.
						// $in_chief .= $v;
					// } else {
						// $in_chief .= $v . ',';
					// }
				// }		
			// } while (!empty($chief_list));		
			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct emp_code
				from employee
				where chief_emp_code = ?
			", array(Auth::id()));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->emp_code;
				$re_emp[] = $e->emp_code;
			}
		
			$emp_list = array_unique($emp_list);
			
			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}					
				
			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct emp_code
					from employee
					where chief_emp_code in ({$in_emp})
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->emp_code;
					$re_emp[] = $e->emp_code;
				}			
				
				$emp_list = array_unique($emp_list);
				
				// Get array keys
				$arrayKeys = array_keys($emp_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		
			
			$re_emp[] = Auth::id();
			$re_emp = array_unique($re_emp);
			
			// Get array keys
			$arrayKeys = array_keys($re_emp);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				

			empty($in_emp) ? $in_emp = "null" : null;	
			$qinput = array();
			$query = "
				Select emp_code, emp_name
				From employee
				Where emp_name like ?
				and emp_code in ({$in_emp})
			";
			
			$qfooter = " Order by emp_name limit 10 ";
			$qinput[] = '%'.$request->emp_name.'%';
			empty($request->section_code) ?: ($query .= " and section_code = ? " AND $qinput[] = $request->section_code);
			empty($request->department_code) ?: ($query .= " and department_code = ? " AND $qinput[] = $request->department_code);
			empty($request->position_code) ?: ($query .= " and position_code = ? " AND $qinput[] = $request->position_code);
			
			$items = DB::select($query.$qfooter,$qinput);			
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
			$query = "
				select a.emp_result_id, a.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, a.position_name, concat(frm.appraisal_level_name,' to ',to_a.appraisal_level_name) as assign, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
				from emp_result a
				left outer join employee b
				on a.emp_code = b.emp_code
				left outer join emp_level c
				on a.emp_code = c.emp_code
				left outer join appraisal_level d
				on c.appraisal_level_id = d.appraisal_level_id
				left outer join appraisal_type e
				on a.appraisal_type_id = e.appraisal_type_id
				left outer join workflow_stage f
				on a.stage_id = f.stage_id
				left outer join appraisal_level frm
				on f.from_appraisal_level_id = frm.appraisal_level_id
				left outer join appraisal_level to_a
				on f.to_appraisal_level_id = to_a.appraisal_level_id
				left outer join appraisal_period g
				on a.period_id = g.period_id
				where 1=1
			";		
				
			empty($request->appraisal_year) ?: ($query .= " and g.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			empty($request->period_no) ?: ($query .= " and g.period_id = ? " AND $qinput[] = $request->period_no);
			empty($request->appraisal_level_id) ?: ($query .= " and c.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
			empty($request->appraisal_type_id) ?: ($query .= " and a.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
			empty($request->department_code) ?: ($query .= " and a.department_code = ? " AND $qinput[] = $request->department_code);
			empty($request->section_code) ?: ($query .= " and a.section_code = ? " AND $qinput[] = $request->section_code);
			empty($request->position_code) ?: ($query .= " and a.position_code = ? " AND $qinput[] = $request->position_code);
			empty($request->emp_code) ?: ($query .= " And b.emp_code = ? " AND $qinput[] = $request->emp_code);
			
			$items = DB::select($query, $qinput);
			
		} else {
			// $query = "
				// select a.emp_result_id, a.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, b.position_name, concat(frm.appraisal_level_name,' to ',to_a.appraisal_level_name) as assign, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
				// from emp_result a
				// left outer join employee b
				// on a.emp_code = b.emp_code
				// left outer join emp_level c
				// on a.emp_code = c.emp_code
				// left outer join appraisal_level d
				// on c.appraisal_level_id = d.appraisal_level_id
				// left outer join appraisal_type e
				// on a.appraisal_type_id = e.appraisal_type_id
				// left outer join workflow_stage f
				// on a.stage_id = f.stage_id
				// left outer join appraisal_level frm
				// on f.from_appraisal_level_id = frm.appraisal_level_id
				// left outer join appraisal_level to_a
				// on f.to_appraisal_level_id = to_a.appraisal_level_id
				// left outer join appraisal_period g
				// on a.period_id = g.period_id
				// where 1=1
				// and b.emp_code = ?			
			// ";
			
			// $qinput[] = Auth::id();
		
			// empty($request->appraisal_year) ?: ($query .= " and g.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			// empty($request->period_no) ?: ($query .= " and g.period_id = ? " AND $qinput[] = $request->period_no);
			// empty($request->appraisal_level_id) ?: ($query .= " and c.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
			// empty($request->appraisal_type_id) ?: ($query .= " and a.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
		// //	empty($request->department_code) ?: ($query .= " and b.department_code = ? " AND $qinput[] = $request->department_code);
			// // empty($request->section_code) ?: ($query .= " and b.section_code = ? " AND $qinput[] = $request->section_code);
			// // empty($request->position_code) ?: ($query .= " and b.position_code = ? " AND $qinput[] = $request->position_code);
			// // empty($request->emp_code) ?: ($query .= " And b.emp_code = ? " AND $qinput[] = $request->emp_code);			
			
			// $query .= "
				// union
				// select a.emp_result_id, a.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, b.position_name, concat(frm.appraisal_level_name,' to ',to_a.appraisal_level_name) as assign, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
				// from emp_result a
				// left outer join employee b
				// on a.emp_code = b.emp_code
				// left outer join emp_level c
				// on a.emp_code = c.emp_code
				// left outer join appraisal_level d
				// on c.appraisal_level_id = d.appraisal_level_id
				// left outer join appraisal_type e
				// on a.appraisal_type_id = e.appraisal_type_id
				// left outer join workflow_stage f
				// on a.stage_id = f.stage_id
				// left outer join appraisal_level frm
				// on f.from_appraisal_level_id = frm.appraisal_level_id
				// left outer join appraisal_level to_a
				// on f.to_appraisal_level_id = to_a.appraisal_level_id
				// left outer join appraisal_period g
				// on a.period_id = g.period_id
				// where 1=1
				// and b.chief_emp_code = ?
			// ";		

			// $qinput[] = Auth::id();
				
			// empty($request->appraisal_year) ?: ($query .= " and g.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			// empty($request->period_no) ?: ($query .= " and g.period_id = ? " AND $qinput[] = $request->period_no);
			// empty($request->appraisal_level_id) ?: ($query .= " and c.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
			// empty($request->appraisal_type_id) ?: ($query .= " and a.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
			// empty($request->department_code) ?: ($query .= " and b.department_code = ? " AND $qinput[] = $request->department_code);
			// empty($request->section_code) ?: ($query .= " and b.section_code = ? " AND $qinput[] = $request->section_code);
			// empty($request->position_code) ?: ($query .= " and b.position_code = ? " AND $qinput[] = $request->position_code);
			// empty($request->emp_code) ?: ($query .= " And b.emp_code = ? " AND $qinput[] = $request->emp_code);	

			// $items = DB::select($query, $qinput);
			
			// $chief_list = array();
			// $rqinput = array();
			
			// foreach ($items as $i) {
				// $chief_list[] = $i->emp_code;
			// }
			
			// $chief_list = array_unique($chief_list);
			
			// // Get array keys
			// $arrayKeys = array_keys($chief_list);
			// // Fetch last array key
			// $lastArrayKey = array_pop($arrayKeys);
			// //iterate array
			// $in_chief = '';
			// foreach($chief_list as $k => $v) {
				// if($k == $lastArrayKey) {
					// //during array iteration this condition states the last element.
					// $in_chief .= $v;
				// } else {
					// $in_chief .= $v . ',';
				// }
			// }					
			
			
			// do {				
				// empty($in_chief) ? $in_chief = "null" : null;
				// $recur_sql = "
					// select a.emp_result_id, a.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, b.position_name, concat(frm.appraisal_level_name,' to ',to_a.appraisal_level_name) as assign, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
					// from emp_result a
					// left outer join employee b
					// on a.emp_code = b.emp_code
					// left outer join emp_level c
					// on a.emp_code = c.emp_code
					// left outer join appraisal_level d
					// on c.appraisal_level_id = d.appraisal_level_id
					// left outer join appraisal_type e
					// on a.appraisal_type_id = e.appraisal_type_id
					// left outer join workflow_stage f
					// on a.stage_id = f.stage_id
					// left outer join appraisal_level frm
					// on f.from_appraisal_level_id = frm.appraisal_level_id
					// left outer join appraisal_level to_a
					// on f.to_appraisal_level_id = to_a.appraisal_level_id
					// left outer join appraisal_period g
					// on a.period_id = g.period_id
					// where 1=1
					// and b.chief_emp_code in ({$in_chief})
				// ";		
					
				// empty($request->appraisal_year) ?: ($recur_sql .= " and g.appraisal_year = ? " AND $rqinput[] = $request->appraisal_year);
				// empty($request->period_no) ?: ($recur_sql .= " and g.period_id = ? " AND $rqinput[] = $request->period_no);
				// empty($request->appraisal_level_id) ?: ($recur_sql .= " and c.appraisal_level_id = ? " AND $rqinput[] = $request->appraisal_level_id);
				// empty($request->appraisal_type_id) ?: ($recur_sql .= " and a.appraisal_type_id = ? " AND $rqinput[] = $request->appraisal_type_id);
				// empty($request->department_code) ?: ($recur_sql .= " and b.department_code = ? " AND $rqinput[] = $request->department_code);
				// empty($request->section_code) ?: ($recur_sql .= " and b.section_code = ? " AND $rqinput[] = $request->section_code);
				// empty($request->position_code) ?: ($recur_sql .= " and b.position_code = ? " AND $rqinput[] = $request->position_code);
				// empty($request->emp_code) ?: ($recur_sql .= " And b.emp_code = ? " AND $rqinput[] = $request->emp_code);				
				
				// $ritems = DB::select($recur_sql,$rqinput);
				
				// $chief_list = array();
				// $rqinput = array();				
				
				// foreach ($ritems as $r) {
					// $items[] = $r;
					// $chief_list[] = $r->emp_code;
				// }
				
				// $chief_list = array_unique($chief_list);
				
				// // Get array keys
				// $arrayKeys = array_keys($chief_list);
				// // Fetch last array key
				// $lastArrayKey = array_pop($arrayKeys);
				// //iterate array
				// $in_chief = '';
				// foreach($chief_list as $k => $v) {
					// if($k == $lastArrayKey) {
						// //during array iteration this condition states the last element.
						// $in_chief .= $v;
					// } else {
						// $in_chief .= $v . ',';
					// }
				// }		
			// } while (!empty($chief_list));
			$re_emp = array();
			
			$emp_list = array();
			
			$emps = DB::select("
				select distinct emp_code
				from employee
				where chief_emp_code = ?
			", array(Auth::id()));
			
			foreach ($emps as $e) {
				$emp_list[] = $e->emp_code;
				$re_emp[] = $e->emp_code;
			}
		
			$emp_list = array_unique($emp_list);
			
			// Get array keys
			$arrayKeys = array_keys($emp_list);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($emp_list as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}					
				
			do {				
				empty($in_emp) ? $in_emp = "null" : null;

				$emp_list = array();			

				$emp_items = DB::select("
					select distinct emp_code
					from employee
					where chief_emp_code in ({$in_emp})
					and is_active = 1			
				");
				
				foreach ($emp_items as $e) {
					$emp_list[] = $e->emp_code;
					$re_emp[] = $e->emp_code;
				}			
				
				$emp_list = array_unique($emp_list);
				
				// Get array keys
				$arrayKeys = array_keys($emp_list);
				// Fetch last array key
				$lastArrayKey = array_pop($arrayKeys);
				//iterate array
				$in_emp = '';
				foreach($emp_list as $k => $v) {
					if($k == $lastArrayKey) {
						//during array iteration this condition states the last element.
						$in_emp .= "'" . $v . "'";
					} else {
						$in_emp .= "'" . $v . "'" . ',';
					}
				}		
			} while (!empty($emp_list));		
			
			$re_emp[] = Auth::id();
			$re_emp = array_unique($re_emp);
			
			// Get array keys
			$arrayKeys = array_keys($re_emp);
			// Fetch last array key
			$lastArrayKey = array_pop($arrayKeys);
			//iterate array
			$in_emp = '';
			foreach($re_emp as $k => $v) {
				if($k == $lastArrayKey) {
					//during array iteration this condition states the last element.
					$in_emp .= "'" . $v . "'";
				} else {
					$in_emp .= "'" . $v . "'" . ',';
				}
			}				

			empty($in_emp) ? $in_emp = "null" : null;			
			$query = "
				select a.emp_result_id, a.emp_code, b.emp_name, d.appraisal_level_name, e.appraisal_type_id, e.appraisal_type_name, a.position_name, concat(frm.appraisal_level_name,' to ',to_a.appraisal_level_name) as assign, f.to_action, a.stage_id, g.period_id, concat(g.appraisal_period_desc,' Start Date: ',g.start_date,' End Date: ',g.end_date) appraisal_period_desc
				from emp_result a
				left outer join employee b
				on a.emp_code = b.emp_code
				left outer join emp_level c
				on a.emp_code = c.emp_code
				left outer join appraisal_level d
				on c.appraisal_level_id = d.appraisal_level_id
				left outer join appraisal_type e
				on a.appraisal_type_id = e.appraisal_type_id
				left outer join workflow_stage f
				on a.stage_id = f.stage_id
				left outer join appraisal_level frm
				on f.from_appraisal_level_id = frm.appraisal_level_id
				left outer join appraisal_level to_a
				on f.to_appraisal_level_id = to_a.appraisal_level_id
				left outer join appraisal_period g
				on a.period_id = g.period_id
				where b.emp_code in ({$in_emp})
			";		
				
			empty($request->appraisal_year) ?: ($query .= " and g.appraisal_year = ? " AND $qinput[] = $request->appraisal_year);
			empty($request->period_no) ?: ($query .= " and g.period_id = ? " AND $qinput[] = $request->period_no);
			empty($request->appraisal_level_id) ?: ($query .= " and c.appraisal_level_id = ? " AND $qinput[] = $request->appraisal_level_id);
			empty($request->appraisal_type_id) ?: ($query .= " and a.appraisal_type_id = ? " AND $qinput[] = $request->appraisal_type_id);
			empty($request->department_code) ?: ($query .= " and a.department_code = ? " AND $qinput[] = $request->department_code);
			empty($request->section_code) ?: ($query .= " and a.section_code = ? " AND $qinput[] = $request->section_code);
			empty($request->position_code) ?: ($query .= " and a.position_code = ? " AND $qinput[] = $request->position_code);
			empty($request->emp_code) ?: ($query .= " And b.emp_code = ? " AND $qinput[] = $request->emp_code);
			
			$items = DB::select($query, $qinput);			
	
		}
		
		
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
	
	public function show(Request $request, $emp_result_id)
	{
		$head = DB::select("
			SELECT b.emp_code, b.emp_name, b.working_start_date, a.position_name, a.section_name, a.department_name, b.chief_emp_code, e.emp_name chief_emp_name, c.appraisal_period_desc, d.appraisal_type_name, a.stage_id, f.status, a.result_score, f.edit_flag
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
			select b.appraisal_item_name, b.structure_id, c.structure_name, d.form_id, d.app_url, c.nof_target_score, a.*, e.perspective_name, a.weigh_score, f.weigh_score total_weigh_score, a.weight_percent, g.weight_percent total_weight_percent
			from appraisal_item_result a
			left outer join appraisal_item b
			on a.appraisal_item_id = b.appraisal_item_id	
			left outer join appraisal_structure c
			on b.structure_id = c.structure_id
			left outer join form_type d
			on c.form_id = d.form_id
			left outer join perspective e
			on b.perspective_id = e.perspective_id
			left outer join structure_result f
			on a.emp_result_id = f.emp_result_id
			and c.structure_id = f.structure_id
			left outer join appraisal_criteria g
			on c.structure_id = g.structure_id
			and b.appraisal_level_id = g.appraisal_level_id	
			where a.emp_result_id = ?
		", array($emp_result_id));
		
		$groups = array();
		foreach ($items as $item) {
			$key = $item->structure_name;
			$hint = array();
			if ($item->form_id == 2) {
				$hint = DB::select("
					select concat(threshold.target_score,' = ',threshold_name) hint
					from threshold
					where is_active = 1
					order by target_score asc				
				");
			}
			if (!isset($groups[$key])) {
				$groups[$key] = array(
					'items' => array($item),
					'count' => 1,
					'form_id' => $item->form_id,
					'form_url' => $item->app_url,
					'nof_target_score' => $item->nof_target_score,
					'total_weight' => $item->total_weight_percent,
					'hint' => $hint,
					'total_weigh_score' => $item->total_weigh_score
				);
			} else {
				$groups[$key]['items'][] = $item;
			//	$groups[$key]['total_weight'] += $item->weight_percent;
				$groups[$key]['count'] += 1;
			}
		}		
	//	$resultT = $items->toArray();
	//	$items['group'] = $groups;
		return response()->json(['head' => $head, 'data' => $items, 'group' => $groups]);		
			
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
			and stage_id > 16
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
				and stage_id > 16
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
			and stage_id > 16
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
				and stage_id > 16
			", array($request->to_appraisal_level_id, $al_id));
		}
		
		return response()->json($items);	
	}
	
	public function update(Request $request, $emp_result_id)
	{
		if ($request->stage_id < 14) {
			return response()->json(['status' => 400, 'data' => 'Invalid action.']);
		}
		
		$checklevel = DB::select("
			select appraisal_level_id
			from emp_level
			where emp_code = ?
		", array(Auth::id()));
		
		if (empty($checklevel)) {
			return response()->json(['status' => 400, 'data' => 'Permission Denied.']);
		} else {
			$alevel = AppraisalLevel::find($checklevel[0]->appraisal_level_id);
			if ($alevel->is_hr == 1) {
				return response()->json(['status' => 400, 'data' => 'Permission Denied for HR user.']);
			}
			
			$checkop = DB::select("
				select appraisal_level_id
				from appraisal_level
				where parent_id = ?
			", array($alevel->appraisal_level_id));
			
			if (empty($checkop)) {
				return response()->json(['status' => 400, 'data' => 'Permission Denied for Operation Level user.']);
			}
			
		}
		
		if (!empty($request->appraisal)) {
			foreach ($request->appraisal as $a) {
				$aresult = AppraisalItemResult::find($a['appraisal_item_result_id']);
				if (empty($aresult)) {
				} else {
					$aresult->score = $a['score'];
					$aresult->updated_by = Auth::id();
					$aresult->save();
				}
			}
		}
		
		$stage = WorkflowStage::find($request->stage_id);
		$emp = EmpResult::find($emp_result_id);
		$emp->stage_id = $request->stage_id;
		$emp->status = $stage->status;
		$emp->updated_by = Auth::id();
		$emp->save();
		
		//if ($request->stage_id == 22 || $request->stage_id == 27 || $request->stage_id == 29) {
		if ($request->stage_id == 19 || $request->stage_id == 25 || $request->stage_id == 29) {
			$items = DB::select("
				select a.appraisal_item_result_id, ifnull(a.score,0) score, a.weight_percent
				from appraisal_item_result a
				left outer join emp_result b
				on a.emp_result_id = b.emp_result_id
				left outer join appraisal_item c
				on a.appraisal_item_id = c.appraisal_item_id
				left outer join appraisal_structure d
				on c.structure_id = d.structure_id
				where d.form_id = 2
				and b.emp_result_id = ?
			", array($emp_result_id));
			
			foreach ($items as $i) {
				$uitem = AppraisalItemResult::find($i->appraisal_item_result_id);
				$uitem->weigh_score = $i->score * $i->weight_percent;
				$uitem->updated_by = Auth::id();
				$uitem->save();
			}	
		}
		
		return response()->json(['status' => 200]);
	}
	
	public function calculate_weight(Request $request)
	{
		$items = DB::select("
			select a.appraisal_item_result_id, ifnull(a.score,0) score, a.weight_percent
			from appraisal_item_result a
			left outer join emp_result b
			on a.emp_result_id = b.emp_result_id
			left outer join appraisal_item c
			on a.appraisal_item_id = c.appraisal_item_id
			left outer join appraisal_structure d
			on c.structure_id = d.structure_id
			where d.form_id = 2
			and b.appraisal_type_id = ?
			and a.period_id = ?
			and a.emp_code = ?
			and a.appraisal_item_id = ?
		", array($request->appraisal_type_id, $request->period_id, $request->emp_code, $request->appraisal_item_id));
		
		foreach ($items as $i) {
			$uitem = AppraisalItemResult::find($i->appraisal_item_result_id);
			$uitem->weigh_score = $i->score * $i->weight_percent;
			$uitem->updated_by = Auth::id();
			$uitem->save();
		}
		
		return response()->json(['status' => 200]);
	
	}
	
}
