<?php

namespace App\Http\Controllers\Admin;

use App\Models\Comment;
use App\Models\Good;
use App\Support\ImageUpload;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class CommentController extends Controller
{
    public function getIndex()
    {
        
        $Comments = Comment::all();
        return view('admin.comment.index',['comments'=>$Comments,'title'=>'回复列表']);
    }
    public function getAdd()
    {
        $Goods = Good::all();
        $Comments = Comment::where('pid',0)->get();
        return view('admin.comment.add',['title'=>'添加用户','goods'=>$Goods,'comments'=>$Comments]);
    }

//    public function postInsert(Requests\CommentInsertRequest $request)
    public function postInsert(Request $request)
    {
        $Comment = new Comment();
        $data = $request->all();
        $Comment->user_id = $data['user_id'];
        $Comment->Good_id = $data['Good_id'];
        $Comment->star = $data['star'];
        $Comment->pid = $data['pid'];
        if($data['pid']==0){
            $Comment->path = 0;
        }else{
            $pComment = Comment::find($data['pid']);
            $Comment->path = $pComment->pid.','.$pComment->id;
        }
        $Comment->status = 1;
        if($request->hasFile('pic'))
        {
            $Comment->img = $this->uploadFile($request);

        }else{
            $Comment->img = config('app.upload_image_dir').'Good.png';
        }
        $Comment->content = $data['content'];

        if($Comment->save()){
            return redirect('admin/comment/add')->with('info','添加成功');
        }else{
            return back()->with('error','添加失败');
        }
    }

    //上传图片
    private function uploadFile(Request $request, string $field = 'pic'): string
    {
        return ImageUpload::store($request, $field);
    }

    public function getEdit(Request $request)
    {
        $Comment = Comment::find($request->route('id'));
        $Goods = Good::all();
        $Comments = Comment::where('pid',0)->get();
        return view('admin.comment.update',['comment'=>$Comment,'comments'=>$Comments,'goods'=>$Goods,'title'=>'修改详情']);
    }

    public function postUpdate(Request $request)
    {
        $data = $request->all();
        $Comment = Comment::where('id',$request->input('id'))->first();
        if (!$Comment) {
            return back()->with('error','评论不存在');
        }

        $Comment -> user_id = $data['user_id'];
        $Comment -> Good_id = $data['Good_id'];
        $Comment -> star = $data['star'];
        $Comment -> pid = $data['pid'];
        $Comment -> status = $data['status'];
        $Comment -> content = $data['content'];
        if($request->hasFile('pic')){
            $this->deleteImage($Comment->img, 'comment.png');
            $Comment->img = $this->uploadFile($request);
        }

        $Comment -> save();
        
        return redirect('/admin/comment')->with('info','更新成功');
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
        $comment = Comment::find($id);
        if ($comment && $comment->delete()) {
            return back()->with('info','删除成功!');
        }
        return back()->with('error','删除失败!');
    }
}
