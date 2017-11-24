<?php

namespace App\Http\Controllers;

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

class DashboardController extends Controller
{

	public function __construct()
	{

	   $this->middleware('jwt.auth');
	}
	
	
	
	public function year_list()
	{
		$items = DB::select("
			select distinct appraisal_year from appraisal_period order by appraisal_year desc		
		");
		return response()->json($items);
	}
	
	public function month_list(Request $request)
	{
		$items = DB::select("
			select period_id, substr(monthname(start_date),1,3) as monthname
                from appraisal_period 
                where appraisal_year = ?
	        and appraisal_frequency_id = 1
                order by period_id asc		
		", array($request->appraisal_year));
		return response()->json($items);
		
	}

	public function balance_scorecard(Request $request)
	{
		$items = DB::select("
			select p.perspective_name, r.appraisal_item_id, i.appraisal_item_name, r.target_value, r.actual_value,r.score
				from appraisal_item_result r, employee e, appraisal_item i, perspective p, appraisal_structure s, form_type f
				where r.emp_code = e.emp_code
				and r.appraisal_item_id = i.appraisal_item_id
				and i.perspective_id = p.perspective_id
				and i.structure_id = s.structure_id
				and s.form_id = f.form_id
				and f.form_name = 'Quantity'
				and e.is_coporate_kpi = 1
				and r.period_id = ?
				order by i.appraisal_item_name	
		", array($request->period_id));
		return response()->json($items);
		
	}

	public function monthly_variance(Request $request)
	{
		$items = DB::select("
			select p.period_no, substr(monthname(p.start_date),1,3), r.target_value, r.actual_value,

			IF(r.score1_target_end > r.score5_target_end,
r.target_value - r.actual_value ,
r.actual_value -r.target_value ) as variance_value

from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity' 
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?
order by p.period_no	
		", array($request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}

	public function monthly_growth(Request $request)
	{
		$items = DB::select("
			select period_no, period_desc, sum(previous_year) as pyear, sum(current_year) as cyear, 
((sum(current_year) - sum(previous_year))/sum(previous_year)) * 100 as growth_percent
from
(select p.period_no, substr(monthname(p.start_date),1,3) as period_desc, r.actual_value as previous_year, 0 as current_year
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_frequency_id = 1
and p.appraisal_year = ? - 1
and r.appraisal_item_id = ?
union
select p.period_no, substr(monthname(p.start_date),1,3) as period_desc, 0 as previous_year, r.actual_value as current_year
from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
where r.emp_code = e.emp_code
and r.appraisal_item_id = i.appraisal_item_id
and i.structure_id = s.structure_id
and s.form_id = f.form_id
and r.period_id = p.period_id
and f.form_name = 'Quantity'
and e.is_coporate_kpi = 1
and p.appraisal_year = ?
and r.appraisal_item_id = ?) as growth
group by period_no, period_desc
order by period_no
		", array($request->appraisal_year,$request->appraisal_item_id,$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}

	public function ytd_monthly_variance(Request $request)
	{
		$items = DB::select("
							select main.period_no
							,main.month_name
							,stv.target_value
							,sav.actual_value
							,svv.variance_value
							from(
							  select p.period_no
							   , substr(monthname(p.start_date),1,3) as month_name
							  from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
							  where r.emp_code = e.emp_code
							  and r.appraisal_item_id = i.appraisal_item_id
							  and i.structure_id = s.structure_id
							  and s.form_id = f.form_id
							  and r.period_id = p.period_id
							  and f.form_name = 'Quantity'
							  and e.is_coporate_kpi = 1
							  and p.appraisal_frequency_id = 1
							  and p.appraisal_year = ?
							  and r.appraisal_item_id = ?
							)main
							left join (
										select period_no
										,(select sum(target_value) as target_value
											 from(
												select p.period_no
													, r.target_value
												 from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
												 where r.emp_code = e.emp_code
												 and r.appraisal_item_id = i.appraisal_item_id
												 and i.structure_id = s.structure_id
												 and s.form_id = f.form_id
												 and r.period_id = p.period_id
												 and f.form_name = 'Quantity'
												 and e.is_coporate_kpi = 1
												 and p.appraisal_frequency_id = 1
												 and p.appraisal_year = ?
												 and r.appraisal_item_id = ?
											)b where b.period_no <= mtv.period_no
										) as target_value
										from(
												select p.period_no
												 from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
												 where r.emp_code = e.emp_code
												 and r.appraisal_item_id = i.appraisal_item_id
												 and i.structure_id = s.structure_id
												 and s.form_id = f.form_id
												 and r.period_id = p.period_id
												 and f.form_name = 'Quantity'
												 and e.is_coporate_kpi = 1
												 and p.appraisal_frequency_id = 1
												 and p.appraisal_year = ?
												 and r.appraisal_item_id = ?
										)mtv
							)stv on stv.period_no = main.period_no
							left join (
										select period_no
										,(select sum(actual_value) as actual_value
											 from(
													select p.period_no
													, r.actual_value
													from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
													where r.emp_code = e.emp_code
													and r.appraisal_item_id = i.appraisal_item_id
													and i.structure_id = s.structure_id
													and s.form_id = f.form_id
													and r.period_id = p.period_id
													and f.form_name = 'Quantity'
													and e.is_coporate_kpi = 1
													and p.appraisal_frequency_id = 1
													and p.appraisal_year = ?
													and r.appraisal_item_id = ?
													and r.actual_value is not null
											)b where b.period_no <= mav.period_no
										) as actual_value
										from(
											select p.period_no
											from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
											where r.emp_code = e.emp_code
											and r.appraisal_item_id = i.appraisal_item_id
											and i.structure_id = s.structure_id
											and s.form_id = f.form_id
											and r.period_id = p.period_id
											and f.form_name = 'Quantity'
											and e.is_coporate_kpi = 1
											and p.appraisal_frequency_id = 1
											and p.appraisal_year = ?
											and r.appraisal_item_id = ?
											and actual_value is not null
										)mav
							)sav on sav.period_no = main.period_no
							left join (
										select period_no
										,(select sum(variance_value) as variance_value
											 from(
												select period_no
												,variance_value
												from(
													select p.period_no,
													IF(r.score1_target_end > r.score5_target_end,
													r.target_value - r.actual_value ,
													r.actual_value -r.target_value ) as variance_value
													from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
													where r.emp_code = e.emp_code
													and r.appraisal_item_id = i.appraisal_item_id
													and i.structure_id = s.structure_id
													and s.form_id = f.form_id
													and r.period_id = p.period_id
													and f.form_name = 'Quantity'
													and e.is_coporate_kpi = 1
													and p.appraisal_frequency_id = 1
													and p.appraisal_year = ?
													and r.appraisal_item_id = ?
												)vv
												where variance_value is not null
											)b where b.period_no <= mvv.period_no
										) as variance_value
										from(
												select period_no
												from(
													select p.period_no,
													IF(r.score1_target_end > r.score5_target_end,
													r.target_value - r.actual_value ,
													r.actual_value -r.target_value ) as variance_value
													from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
													where r.emp_code = e.emp_code
													and r.appraisal_item_id = i.appraisal_item_id
													and i.structure_id = s.structure_id
													and s.form_id = f.form_id
													and r.period_id = p.period_id
													and f.form_name = 'Quantity'
													and e.is_coporate_kpi = 1
													and p.appraisal_frequency_id = 1
													and p.appraisal_year = ?
													and r.appraisal_item_id = ?
												)vv
												where variance_value is not null
										)mvv
							)svv on svv.period_no = main.period_no
							order by main.period_no
		", array($request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}


	public function ytd_monthly_growth(Request $request)
	{
		$items = DB::select("

						select main.period_no
						,main.month_name
						,IFNULL(sub.pyear, 0) as pyear
						,IFNULL(sub.cyear, 0) as cyear
						,IFNULL(sub.growth_percent, 0) as growth_percent
						from(
						  select p.period_no
						   , substr(monthname(p.start_date),1,3) as month_name
						  from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
						  where r.emp_code = e.emp_code
						  and r.appraisal_item_id = i.appraisal_item_id
						  and i.structure_id = s.structure_id
						  and s.form_id = f.form_id
						  and r.period_id = p.period_id
						  and f.form_name = 'Quantity'
						  and e.is_coporate_kpi = 1
						  and p.appraisal_frequency_id = 1
						  and p.appraisal_year = ?
						  and r.appraisal_item_id = ?
						)main
						left join (
						SELECT period_no
						, sum(previous_year) as pyear
						, sum(current_year) as cyear
						, ((sum(current_year) - sum(previous_year))/sum(previous_year)) * 100 as growth_percent
						from(
									select period_no
									,(select sum(actual_value) as previous_year
										 from(
												select p.period_no
												, r.actual_value
												from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
												where r.emp_code = e.emp_code
												and r.appraisal_item_id = i.appraisal_item_id
												and i.structure_id = s.structure_id
												and s.form_id = f.form_id
												and r.period_id = p.period_id
												and f.form_name = 'Quantity'
												and e.is_coporate_kpi = 1
												and p.appraisal_frequency_id = 1
												and p.appraisal_year = ? - 1
												and r.appraisal_item_id = ?
												and r.actual_value is not null
										)b where b.period_no <= mav.period_no
									) as previous_year
									, 0 as current_year
									from(
										select p.period_no
										from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
										where r.emp_code = e.emp_code
										and r.appraisal_item_id = i.appraisal_item_id
										and i.structure_id = s.structure_id
										and s.form_id = f.form_id
										and r.period_id = p.period_id
										and f.form_name = 'Quantity'
										and e.is_coporate_kpi = 1
										and p.appraisal_frequency_id = 1
										and p.appraisal_year = ? - 1
										and r.appraisal_item_id = ?
										and actual_value is not null
									)mav
						union
									select period_no
									,0 as previous_year
									,(select sum(actual_value) as current_year
										 from(
												select p.period_no
												, r.actual_value
												from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
												where r.emp_code = e.emp_code
												and r.appraisal_item_id = i.appraisal_item_id
												and i.structure_id = s.structure_id
												and s.form_id = f.form_id
												and r.period_id = p.period_id
												and f.form_name = 'Quantity'
												and e.is_coporate_kpi = 1
												and p.appraisal_frequency_id = 1
												and p.appraisal_year = ?
												and r.appraisal_item_id = ?
												and r.actual_value is not null
										)b where b.period_no <= mav.period_no
									) as actual_value
									from(
										select p.period_no
										from appraisal_item_result r, employee e, appraisal_item i, appraisal_structure s, form_type f, appraisal_period p
										where r.emp_code = e.emp_code
										and r.appraisal_item_id = i.appraisal_item_id
										and i.structure_id = s.structure_id
										and s.form_id = f.form_id
										and r.period_id = p.period_id
										and f.form_name = 'Quantity'
										and e.is_coporate_kpi = 1
										and p.appraisal_frequency_id = 1
										and p.appraisal_year = ?
										and r.appraisal_item_id = ?
										and actual_value is not null
									)mav
						)growth
						group by period_no
						)sub on sub.period_no = main.period_no
		
		", array($request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id,
			$request->appraisal_year,$request->appraisal_item_id));
		return response()->json($items);
		
	}


	

	
   
}
