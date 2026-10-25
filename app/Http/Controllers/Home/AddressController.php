<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Models\Address;


class AddressController extends Controller
{
    //存储ajax返回的信息
    private $data = [];
    /**
     * ajax添加用户地址操作
     */
    public function getAdd(Request $request)
    {
        //获取登录用户的uid
        $uid = session('uid');
        $address = new Address;
        $address->user_id = $uid;
        $address->consignee = $request->input('uname');
        $address->tel = $request->input('phone');
        $address->province = $request->input('province');
        $address->city = $request->input('city');
        $address->district = $request->input('country');
        $address->postcode = $request->input('zipcode');
        $address->address = $request->input('address');

        if($address->save()){
            $this->data['status'] = 0;
            $this->data['msg'] = $address->id;
            return response()->json($this->data);
        }else{
            $this->data['status'] = 1;
            $this->data['msg'] = '服务器出错,请稍后重试';
            return response()->json($this->data);
        }
    }

    public function getDel(Request $request)
    {
        // 新地址 /address/delete/{id}; 旧地址 /address/del?id=xx 仍可用
        $ad_id = $request->route('id') ?? $request->input('id');
        $uid = session('uid');
        $address = Address::where('user_id',$uid)->find($ad_id);

        if(empty($address)){
            $this->data['status'] = 1;
            $this->data['msg'] = '地址不存在';
            return response()->json($this->data);
        }

        if($address->delete()){
            $this->data['status'] = 0;
            $this->data['msg'] = 'ok';
            return response()->json($this->data);
        }else{
            $this->data['status'] = 1;
            $this->data['msg'] = '请刷新网页';
            return response()->json($this->data);
        }
    }
    public static function getAddressByUid($uid)
    {
        $addresses = Address::where('user_id',$uid)->orderBy('id','desc')->get();

        if(!empty($addresses)){
            return $addresses;
        }

    }
}
