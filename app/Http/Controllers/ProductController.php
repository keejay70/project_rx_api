<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Imarket\Checkout\Models\Checkout;
use Increment\Imarket\Product\Models\Product;
use Increment\Imarket\Merchant\Models\Merchant;
use Increment\Imarket\Merchant\Models\Location;
use Increment\Imarket\Product\Http\ProductImageController;
use Increment\Common\Rating\Http\RatingController;
use Increment\Common\Rating\Models\Rating;
use Increment\Imarket\Cart\Models\Cart;
use Carbon\Carbon;

class ProductController extends APIController
{
    protected $dashboard = array(      
        "data" => null,
        "error" => array(),// {status, message}
        "debug" => null,
        "request_timestamp" => 0,
        "timezone" => 'Asia/Manila'
    );
    
    public static function LongLatDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
      {
        if (is_null($latitudeFrom) || is_null($longitudeFrom) || is_null($latitudeTo) || is_null($longitudeTo)) {
          return null;
        }
        $latitudeFrom = floatval($latitudeFrom);
        $longitudeFrom = floatval($longitudeFrom);
        $latitudeTo = floatval($latitudeTo);
        $longitudeTo = floatval($longitudeTo);
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);
        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
          pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);
      
        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
      }

    /**
     * @param
     * {
     *    latitude,
     *    longitude,
     *    limit,
     *    offset,
     *    condition: e.g {
     *        "condition": [
     *            {"column": "category", "clause": "=", "value": "Burger"},
     *            {"column": "category", "clause": "=", "value": "Fast Food"}
     *         ],
     *    }
     * }
     */
    public function retrieveByCategory(Request $request){
        $dashboardarr = [];
        $conditions = $request['condition'];
        foreach ($conditions as $condition){
            $datatemp = [];

            $result = Product::select('products.id','products.account_id','products.merchant_id','products.title', 'products.description','products.status','products.category', 'locations.latitude', 'locations.longitude', 'locations.route')
                ->leftJoin('locations', 'products.account_id',"=","locations.account_id")
                ->distinct("products.id")
                ->where($condition['column'],$condition['value'])
                ->limit($request['limit'])
                ->offset($request['offset'])->get();

            for($i=0; $i<count($result); $i++){
                $result[$i]["distance"] = $this->LongLatDistance($request["latitude"],$request["longitude"],$result[$i]["latitude"], $result[$i]["longitude"]);
                if ($result[$i]["distance"] <= 30){
                    $result[$i]["rating"] = app('Increment\Common\Rating\Http\RatingController')->getRatingByPayload("merchant", $result[$i]["account_id"]);
                    $result[$i]["image"] = app('Increment\Imarket\Product\Http\ProductImageController')->getProductImage($result[$i]["id"], "featured");
                    array_push($datatemp, $result[$i]);
                }
            }

            array_push($dashboardarr, $datatemp);
        }

        
        $dashboard["request_timestamp"]= date("Y-m-d h:i:s");
        $dashboard["data"] = $dashboardarr;
        return $dashboard;
    }

    /**
     * @param
     * {
     *    latitude,
     *    longitude,
     *    limit,
     *    offset,
     * }
     */
    public function retrieveByFeatured(Request $request){
        $dashboardarr = [];
        $datatemp = [];
        $conditions = $request['condition'];
        $modifiedrequest = new Request([]);
        $modifiedrequest['limit'] = $request['limit'];

        $result = Product::select('products.id', 'products.account_id','products.merchant_id','products.category','products.title', 'products.description','locations.latitude', 'locations.longitude', 'locations.route')
            ->leftJoin('locations', 'products.account_id',"=","locations.account_id")
            ->where("status","featured")
            ->distinct("products.id")
            ->limit($modifiedrequest['limit'])
            ->offset($request['offset'])
            ->get();

        for($i=0; $i<count($result); $i++){
            $result[$i]["distance"] = $this->LongLatDistance($request["latitude"],$request["longitude"],$result[$i]["latitude"], $result[$i]["longitude"]);
            if ($result[$i]["distance"] <= 30){
                $result[$i]["rating"] = app('Increment\Common\Rating\Http\RatingController')->getRatingByPayload("merchant", $result[$i]["account_id"]);
                $result[$i]["image"] = app('Increment\Imarket\Product\Http\ProductImageController')->getProductImage($result[$i]["id"], "featured");
                array_push($datatemp, $result[$i]);            
            }
        }

        array_push($dashboardarr, $datatemp);        
        $dashboard["request_timestamp"]= date("Y-m-d h:i:s");
        $dashboard["data"] = $dashboardarr;
        return $dashboard;
    }

    /**
     * @param
     * {
     *    limit,
     *    offset,
     * }
     */
    public function getCategories(Request $request){
        //limit and offset only
        $this->model = new Product;
        (isset($request['offset'])) ? $this->model = $this->model->offset($request['offset']) : null;
        (isset($request['limit'])) ? $this->model = $this->model->limit($request['limit']) : null;
        $result = $this->model->select('category')->where('category', '!=', null)->groupBy('category')->distinct()->get();
        return $result;
    }

    /**
     * @param
     * {
     *    limit,
     *    offset,
     *    sort,
     *    latitude,
     *    longitude
     * } => grab all shops
     * 
     * if { id } only => grab by shop
     */
    public function retrieveByShop(Request $request){
        $dashboardarr = [];
        $datatemp = [];
        $conditions = $request['condition'];
        $modifiedrequest = new Request([]);
        if (isset($request["id"])){
            $result = Merchant::select()
                ->where("merchants.id",$request['id'])
                ->leftJoin('locations','merchants.account_id',"=", "locations.account_id")->get();
            for($i=0; $i<count($result); $i++){
                $result[$i]["distance"] = $this->LongLatDistance($request["latitude"],$request["longitude"],$result[$i]["latitude"], $result[$i]["longitude"]);
                if ($result[$i]["distance"] <= 30){
                    $result[$i]["rating"] = app('Increment\Common\Rating\Http\RatingController')->getRatingByPayload("merchant", $result[$i]["account_id"]);
                    $result[$i]["image"] = app('Increment\Imarket\Product\Http\ProductImageController')->getProductImage($result[$i]["id"], "featured");
                    array_push($dashboardarr, $result[$i]);                }
            }
        }else{
            $result = Merchant::select("merchants.id", "merchants.code","merchants.account_id", "merchants.name", "merchants.prefix", "merchants.logo", "locations.latitude","locations.longitude","locations.route","locations.locality")
                ->leftJoin('locations', 'merchants.account_id',"=","locations.account_id")
                ->distinct("merchants.id")
                ->limit($request['limit'])
                ->offset($request['offset'])
                ->orderBy($request['sort'], 'desc')->get();
            for($i=0; $i<count($result); $i++){
                $result[$i]["distance"] = $this->LongLatDistance($request["latitude"],$request["longitude"],$result[$i]["latitude"], $result[$i]["longitude"]);
                if ($result[$i]["distance"] <= 30){
                    $result[$i]["rating"] = app('Increment\Common\Rating\Http\RatingController')->getRatingByPayload("merchant", $result[$i]["account_id"]);
                    $result[$i]["image"] = app('Increment\Imarket\Product\Http\ProductImageController')->getProductImage($result[$i]["id"], "featured");
                    array_push($datatemp, $result[$i]);
                }
            }
            array_push($dashboardarr, $datatemp);
        }
        $dashboard["request_timestamp"]= date("Y-m-d h:i:s");
        $dashboard["data"] = $dashboardarr;
        return $dashboard;
    }

}
    