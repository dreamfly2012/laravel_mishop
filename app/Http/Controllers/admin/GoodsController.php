<?php

namespace App\Http\Controllers\Admin;


use App\Models\Cate;
use App\Models\Good;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class GoodsController extends Controller
{
    public function getIndex()
    {
        
        $goods = Good::all();
        
        return view('admin.goods.index',['goods'=>$goods,'title'=>'商品列表']);
    }
    public function getAdd()
    {
        $cates = Cate::all();
        return view('admin.goods.add',['cates'=>$cates,'title'=>'添加商品']);
    }
    public function postInsert(Request $request)
    {

//        dd($_FILES['img']);
//        $imgs = Input::file('img');
//        dd($imgs[0]);
//        dd($request->file('showImg'));
//        dd($request->file('img'));
//        dd($request->all());
        $good = new Good();
        $data = $request->all();
        $good->title = $data['title'];
        $good->cate_id = $data['cate_id'];
        $good->price = $data['price'];
        $good->status = 1;
        if($request->hasFile('showImg'))
        {
            $good->showImg = $this->uploadFile($request, 'showImg');

        }else{
            $good->showImg = config('app.upload_image_dir').'good.png';
        }
        if($request->file('img'))
        {
            $good->img = $this->uploadFiles($request);
        }

        $good->content = $data['content'];


        if($good->save()){
            return redirect('admin/sku/add?id='.$good->id)->with('info','添加成功');
        }else{
            return back()->with('error','添加失败');
        }
    }

        private function uploadFile(Request $request, string $field): string
    {
        $file = $request->file($field);
        $dir = config('app.upload_image_dir');
        $name = $dir . rand(1000000, 9000000) . time() . '.' . strtolower($file->getClientOriginalExtension());
        $file->move(public_path($dir), basename($name));
        return $name;
    }

        private function uploadFiles(Request $request): string
    {
        $names = '';
        $i = 0;
        foreach ((array) $request->file('img') as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }
            $dir = config('app.upload_image_dir');
            $name = $dir . rand(1000000, 9000000) . time() . ($i++) . '.' . strtolower($file->getClientOriginalExtension());
            $file->move(public_path($dir), basename($name));
            $names .= $name . ',';
        }
        return $names;
    }


   

    public function getEdit(Request $request)
    {
        $good = Good::where('id',$request->only('id'))->first();
        $cates = Cate::where('pid','0')->get();
        return view('admin.goods.update',['good'=>$good,'cates'=>$cates,'title'=>'修改详情']);
    }

    public function postUpdate(Request $request)
    {
        $data = $request->all();
        $good = Good::where('id',$request->only('id'))->first();

        //判断用户名
        if($good['title'] != $request->input('title')) {

            if(Good::where('title','like', $request->input('title'))->first()){

                return back()->with('error','文章标题已存在');

            }
        }

        $good -> title = $data['title'];
        $good -> cate_id = $data['cate_id'];
        $good -> price = $data['price'];
        $good -> status = $data['status'];
        $good->content = $data['content'];

        if($request->hasFile('showImg')){
            $this->deleteImage($good->showImg, 'good.png');
            $good->showImg = $this->uploadFile($request, 'showImg');
        }

        if($request->file('img'))
        {
            $pics = Good::select('img')->where('id',$data['id'])->get();
            foreach (explode(',',$pics[0]->img) as $value)
            {
                if(file_exists($value)){
                    unlink($value);
                }
            }
            $good->img = $this->uploadFiles($request);
        }
        $good -> save();

        return redirect('admin/good')->with('info','更新成功');
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

        if(Good::where('id',$id)->first()->delete()){
            return back()->with('info','删除成功!');
        }else{
            return back()->with('error','删除失败!');
        }
    }

}
