<?php

namespace App\Http\Controllers\Admin;


use App\Models\Cate;
use App\Models\Good;
use App\Support\ImageUpload;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

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
            return redirect('/admin/sku/add/'.$good->id)->with('info','添加成功');
        }else{
            return back()->with('error','添加失败');
        }
    }

    private function uploadFile(Request $request, string $field): string
    {
        return ImageUpload::store($request, $field);
    }

    private function uploadFiles(Request $request): string
    {
        return ImageUpload::storeMany($request, 'img');
    }


   

    public function getEdit(Request $request)
    {
        $good = Good::where('id', $request->route('id'))->first();
        $cates = Cate::where('pid','0')->get();
        return view('admin.goods.update',['good'=>$good,'cates'=>$cates,'title'=>'修改详情']);
    }

    public function postUpdate(Request $request)
    {
        $data = $request->all();
        $good = Good::where('id',$request->input('id'))->first();
        if (!$good) {
            return back()->with('error','商品不存在');
        }

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
            $pics = Good::select('img')->where('id',$data['id'])->first();
            if ($pics && $pics->img) {
                foreach (explode(',',$pics->img) as $value)
                {
                    if ($value !== '' && is_file(public_path($value))) {
                        @unlink(public_path($value));
                    }
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
        $good = Good::find($id);
        if (!$good) {
            return back()->with('error','商品不存在!');
        }
        if($good->delete()){
            return back()->with('info','删除成功!');
        }else{
            return back()->with('error','删除失败!');
        }
    }

}
