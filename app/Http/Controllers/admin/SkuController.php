<?php

namespace App\Http\Controllers\Admin;


use App\Models\Cate;
use App\Models\Good;
use App\Models\Sku;
use App\Support\ImageUpload;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

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
        $good = Good::find($request->route('goodId'));

        return view('admin.sku.sku',['title'=>'编辑sku','good'=>$good]);
    }
    public function getEdit(Request $request)
    {
        $sku = Sku::find($request->route('id'));
        if (!$sku) {
            return redirect('/admin/sku')->with('error','规格不存在');
        }
        
        $good = Good::find($sku->good_id);
        
        

        return view('admin.sku.update',['title'=>'编辑sku','sku'=>$sku,'good'=>$good]);
    }
    
    public function getInsert(Request $request)
    {
        $data = $request->only('title','good_id');
       
        if(Sku::where('good_id',$data['good_id'])->where('title',$data['title'])->exists()){
            return response((string)Sku::where('good_id',$data['good_id'])->count());
        }else{
            return response('0');
        }
    }
    public function postInsert(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:200',
            'good_id'    => 'required|integer|exists:goods,id',
            'falsePrice' => 'required|numeric|min:0',
            'price'      => 'required|numeric|min:0',
            'color'      => 'required|string|max:50',
            'attr'       => 'required|string|max:50',
            'info'       => 'nullable|string',
            'stock'      => 'required|integer|min:0',
            'img'        => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        if (Sku::where('good_id',$data['good_id'])->where('title',$data['title'])->exists()) {
            return response('该商品下已存在同名规格', 422);
        }

        $sku = new Sku();
        $sku->title = $data['title'];
        $sku->good_id = $data['good_id'];
        $sku->falsePrice = $data['falsePrice'];
        $sku->price = $data['price'];
        $sku->color = $data['color'];
        $sku->attr = $data['attr'];
        $sku->info = $data['info'] ?? '';
        $sku->stock = $data['stock'];
        $sku->status = 1;

        if($request->hasFile('img'))
        {
            $sku->img = $this->uploadFile($request);

        }else{
            $sku->img = config('app.upload_image_dir').'sku.png';
        }

        $sku->save();

        return response('ok');
    }



    private function uploadFile(Request $request, string $field = 'img'): string
    {
        return ImageUpload::store($request, $field);
    }

    
    public function getAjaxEdit(Request $request)
    {
        $data = $request->only('title');
        $sku = Sku::where('title',$data['title'])->first();
        return response()->json($sku);
    }

    public function postUpdate(Request $request)
    {
        $data = $request->validate([
            'id'         => 'required|integer',
            'title'      => 'required|string|max:200',
            'good_id'    => 'required|integer|exists:goods,id',
            'falsePrice' => 'required|numeric|min:0',
            'price'      => 'required|numeric|min:0',
            'color'      => 'required|string|max:50',
            'attr'       => 'required|string|max:50',
            'info'       => 'nullable|string',
            'stock'      => 'required|integer|min:0',
            'img'        => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);

        $sku = Sku::find($data['id']);
        if (!$sku) {
            return back()->with('error','规格不存在');
        }

        // 同一商品下规格名不能重复（原代码查的是 goods 表，永远查不到）
        if($sku->title != $data['title']) {
            if(Sku::where('good_id',$data['good_id'])->where('title',$data['title'])->exists()){
                return back()->with('error','该商品下已存在同名规格');
            }
        }
        $sku->title = $data['title'];
        $sku->good_id = $data['good_id'];
        $sku->falsePrice = $data['falsePrice'];
        $sku->price = $data['price'];
        $sku->color = $data['color'];
        $sku->attr = $data['attr'];
        $sku->info = $data['info'] ?? '';
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


    public function getAjaxDelete(Request $request)
    {
        $data = $request->only('title','good_id');

        // 按 商品 + 名称 精确定位，避免误删其他商品的同名规格
        $sku = Sku::where('good_id',$data['good_id'])->where('title',$data['title'])->first();

        if($sku && $sku->delete()){
            return response((string)Sku::where('good_id',$data['good_id'])->count());
        }else{
            return response('0');
        }
    }
    public function getDelete(Request $request)
    {
        $id = $request->route('id');
        $sku = Sku::find($id);
        if (!$sku) {
            return back()->with('error','规格不存在');
        }
        if($sku->delete())
        {
            return back()->with('info','删除成功');
        }
        return back()->with('error','删除失败');
    }

}
