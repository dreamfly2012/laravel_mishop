<?php

namespace App\Http\Controllers\Admin;

use App\Models\Cate;
use App\Models\User;
use App\Support\ImageUpload;
use Illuminate\Http\Request;


use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Hash;

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
        $user->level = '0';

        if($user->save()){
            return redirect('admin/user/add')->with('info','添加成功');
        }else{
            return back()->with('error','添加失败');
        }
    }

    //上传头像
    private function uploadFile(Request $request, string $field = 'pic'): string
    {
        return ImageUpload::store($request, $field);
    }


    //编辑信息
    public function getEdit(Request $request)
    {
        $user = User::where('id', $request->route('id'))->first();

        return view('admin.user.update',['user'=>$user,'title'=>'添加用户']);
    }
    
    //执行编辑信息
    public function postUpdate(Requests\UserUpdateRequest $request)
    {
        $user = User::where('id',$request->input('id'))->first();
        if (!$user) {
            return back()->with('error','用户不存在');
        }

        $username = $request->input('username');

        //判断用户名
        if($user->username != $username) {

            if(User::where('username',$username)->where('id','!=',$user->id)->exists()){

                return back()->with('error','用户名已存在');

            }
        }
        
        $user -> username = $username;
        $user -> email = $request -> input('email');
        $user -> phone = $request -> input('phone');
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
        // 仅允许删除上传目录内的文件, 防止库内路径被篡改后路径穿越删除项目其他文件
        if (!str_starts_with($img, $dir)) {
            return;
        }
        $full = public_path($img);
        $base = rtrim(public_path($dir), '/');
        $real = realpath($full);
        if ($real === false || strncmp($real, $base . '/', strlen($base) + 1) !== 0) {
            return;
        }
        if (is_file($full)) {
            @unlink($full);
        }
    }


    public function getDelete(Request $request)
    {
        $id = $request->route('id');
        if ((int)$id === 1) {
            return back()->with('error','不能删除超级管理员');
        }
        $user = User::find($id);
        if($user && $user->delete()){
            return back()->with('info','删除成功!');
        }
        return back()->with('error','删除失败!');
    }
}
