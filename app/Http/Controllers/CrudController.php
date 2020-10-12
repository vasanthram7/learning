<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Crud;
use DB, Log, Hash, Validator, Exception, Setting;
use App\Helpers\Helper;


class CrudController extends Controller
{
    //

    public function createRecord(Request $request) {
    	try {
    		DB::begintransaction();
    		
            $rules = [
                'name' => 'required|max:191',
                'course' => 'required',
                'email' => 'required',
                'phone' => 'required',
            ];
            Helper::custom_validator($request->all(),$rules);

            $crud = Crud::find($request->id) ?? new Crud;

            $success_code = $crud->id ? 131 : 130;
		    
		    $crud->name = $request->name ?: $crud->name; 
		    $crud->course = $request->course ?: $crud->course;
		    $crud->email = $request->email ?: $crud->email;
		    $crud->phone = $request->phone ?: $crud->phone;
		    
		    if($crud->save()) {

                DB::commit(); 

                return response()->json(["message" => "New record created" ], 201);

            }

		    throw new Exception(api_error(128), 128);
		}
		catch(Exception $e) {

            return $this->sendError($e->getMessage(), $e->getCode());
        
        }
  	}


  	

  	
  	public function getAllRecords() {
  		try {
    	$crud = Crud::get()->toJson(JSON_PRETTY_PRINT);
    
    	return response($crud, 200);
    	}
    	catch(Exception $e) {

            return $this->sendError($e->getMessage(), $e->getCode());
        
        }
  	}

  	public function getRecord($id) {
  		try{

  			


  			if (Crud::where('id', $id)->exists()) {
	        $crud = Crud::where('id', $id)->get()->toJson(JSON_PRETTY_PRINT);
	        return response($crud, 200);	        
	      	}
	      	else {
	        return response()->json([ "message" => "Record not found"  ], 404);
	      	} 
  		}
  		catch(Exception $e) {

  			//else {
	        return response()->json([ "message" => "Record not found"  ], 404);
	      //}
            
        
        }
	    
  	}




	public function updateRecord(Request $request, $id) {
		try { 
			
			if (Crud::where('id', $id)->exists()) {
	        $crud = Crud::find($id);
	        $crud->name = is_null($request->name) ? $crud->name : $request->name;
	        $crud->course = is_null($request->course) ? $crud->course : $request->course;
	        $crud->save();

	        return response()->json([
	            "message" => "records updated successfully"
	        ], 200);
	        } else {
	        return response()->json([
	            "message" => "Record not found"
	        ], 404);
	        
	    }
			
		} 
		catch (Exception $e) {
			
		}

	    
	}


	public function deleteRecord ($id) {
		
	try {

		if(Crud::where('id', $id)->exists()) {
        $crud = Crud::find($id);
        $crud->delete();

        return response()->json([
          "message" => "Records deleted"
        ], 202);
      } else {
        return response()->json([
          "message" => "Record not found"
        ], 404);
      }
	} 

	catch (Exception $e) {
		
	}

      
    }


}
