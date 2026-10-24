<?php

namespace App\Http\Controllers\Admin;


use App\Models\Cate;
use App\Models\Good;
use App\Models\Sku;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;

class SkuController extends Controller
{
    public function getIndex()
    {

        $sku = Sku::all();


        return view('admin.sku.index',['title'=>'商品种类列表','skus'=>$sku]);

        /**没有sku列表页**/
    }
    public function getAdd(Request $request)
    {
        $data = $request->only('id');
        $good = Good::find($data['id']);

        return view('admin.sku.sku',['title'=>'编辑sku','good'=>$good]);
    }
    public function getEdit(Request $request)
    {
        $data = $request->only('id');

        $sku = Sku::find($data['id']);
        
        $good = Good::find($sku->good_id);
        
        

        return view('admin.sku.update',['title'=>'编辑sku','sku'=>$sku,'good'=>$good]);
    }
    
    public function getInsert(Request $request)
    {
        $data = $request->only('title','good_id');
       
        if(Sku::where('title',$data['title'])->get()->count()){
            return response((string)Sku::where('good_id',$data['good_id'])->count());
        }else{
            return response('0');
        }
    }
    public function postInsert(Request $request)
    {
        
        $sku = new Sku();
        $data = $request->all();
        $sku->title = $data['title'];
        $sku->good_id = $data['good_id'];
        $sku->falsePrice = $data['falsePrice'];
        $sku->price = $data['price'];
        $sku->color = $data['color'];
        $sku->attr = $data['attr'];
        $sku->info = $data['info'];
        $sku->stock = $data['stock'];
        $sku->status = 1;

        if($request->hasFile('img'))
        {
            $sku->img = $this->uploadFile($request);

        }else{
            $sku->img = config('app.upload_image_dir').'sku.png';
        }

        $sku->save();
    }



        private function uploadFile(Request $request, string $field = 'img'): string
    {
        $file = $request->file($field);
        $dir = config('app.upload_image_dir');
        $name = $dir . rand(1000000, 9000000) . time() . '.' . strtolower($file->getClientOriginalExtension());
        $file->move(public_path($dir), basename($name));
        return $name;
    }

    
    public function getAjaxEdit(Request $request)
    {
        $data = $request->only('title');
        $sku = Sku::where('title',$data['title'])->first();
        return response()->json($sku);
    }

    public function postUpdate(Request $request)
    {
        $data = $request->all();
        $sku = Sku::find($data['id']);

        //判断用户名
        if($sku['title'] != $request->title) {

            if(Good::where('title','like', $request->title)->first()){

                return back()->with('error','文章标题已存在');

            }
        }
        $sku->title = $data['title'];
        $sku->good_id = $data['good_id'];
        $sku->falsePrice = $data['falsePrice'];
        $sku->price = $data['price'];
        $sku->color = $data['color'];
        $sku->attr = $data['attr'];
        $sku->info = $data['info'];
        $sku->stock = $data['stock'];
        $sku->status = 1;
        
        if($request->hasFile('img')){
            $this->deleteImage($sku->img, 'sku.png');
            $sku->img = $this->uploadFile($request);
        }

        $sku -> save();

        return back()->with('info','更新成功');
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


    public function getAjaxDelete(Request $request)
    {
        
        
        $data = $request->only('title','good_id');

        if(Sku::where('title',$data['title'])->delete()){
            return response((string)Sku::where('good_id',$data['good_id'])->count());
        }else{
            return response('0');
        }
    }
    public function getDelete(Request $request)
    {
        $data = $request->only('id');

        if(Sku::where('id',$data['id'])->delete())
        {
            return back()->with('info','删除成功');
        }
    }

}
