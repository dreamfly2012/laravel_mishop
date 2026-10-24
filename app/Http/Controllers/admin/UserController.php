<?php

namespace App\Http\Controllers\Admin;

use App\Models\Cate;
use App\Models\User;
use Illuminate\Http\Request;


use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Input;

class UserController extends Controller
{
    public function getIndex()
    {
        $users = User::all();
        return view('admin.user.index',['users'=>$users,'title'=>'用户列表']);
    }
    public function getAdd()
    {
        return view('admin.user.add',['title'=>'添加用户']);
    }

    public function postInsert(Requests\UserInsertRequest $request)
    {
        $user = new User();
        $data = $request->only('username','password','email','phone');
        $user->password = Hash::make($data['password']);
        $user->status = 1;
        if($request->hasFile('pic'))
        {
            $user->pic = $this->uploadFile($request);

        }else{
            $user->pic = config('app.upload_image_dir').'user.png';
        }
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->phone = $data['phone'];

        if($user->save()){
            return redirect('admin/user/add')->with('info','添加成功');
        }else{
            return back()->with('error','添加失败');
        }
    }

    //上传头像
        private function uploadFile(Request $request, string $field = 'pic'): string
    {
        $file = $request->file($field);
        $dir = config('app.upload_image_dir');
        $name = $dir . rand(1000000, 9000000) . time() . '.' . strtolower($file->getClientOriginalExtension());
        $file->move(public_path($dir), basename($name));
        return $name;
    }


    //编辑信息
    public function getEdit(Request $request)
    {
        $user = User::where('id',$request->only('id'))->first();

        return view('admin.user.update',['user'=>$user,'title'=>'添加用户']);
    }
    
    //执行编辑信息
    public function postUpdate(Requests\UserUpdateRequest $request)
    {
        $user = User::where('id',$request->only('id'))->first();

        //判断用户名
        if($user['username'] != $request->username) {

            if(User::where('username','like', $request->username)->first()){

                return back()->with('error','用户名已存在');

            }
        }
        
        $user -> username = $request -> username;
        $user -> email = $request -> email;
        $user -> phone = $request -> phone;
        if($request->hasFile('pic')){
            $this->deleteImage($user->pic, 'user.png');
            $user->pic = $this->uploadFile($request);
        }

        $user -> save();
        return redirect('admin/user')->with('info','更新成功');
    }
    
        //删除旧图片文件(与默认占位图相同的跳过)
    private function deleteImage(?string $img, string $default): void
    {
        $dir = config('app.upload_image_dir');
        if (!$img || $img == $dir . $default) {
            return;
        }
        $full = public_path($img);
        if (is_file($full)) {
            @unlink($full);
        }
    }


    public function getDelete(Request $request)
    {
        $id = $request->input('id');
        if(User::where('id',$id)->first()->delete()){
            return back()->with('info','删除成功!');
        }else{
            return back()->with('error','删除失败!');
        }
    }
}
