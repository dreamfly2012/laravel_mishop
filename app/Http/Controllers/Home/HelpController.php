<?php

namespace App\Http\Controllers\Home;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCate;

class HelpController extends Controller
{
    //
    public function getArticle(Request $request)
    {
        // 新地址 /help/article/{id}; 旧地址 /help/article?id=xx 301 到新格式
        $id = $request->route('id');

        if (is_null($id) && $request->filled('id')) {
            return redirect('/help/article/'.(int)$request->input('id'), 301);
        }

        $article = HelpArticle::where('status',1)->findOrFail($id);
        if(empty($article)){
            abort(404);
        }

        
        return view('home.help.help',[
                'article'=>$article,
    
                'request'=>$request
            ]);
    }

    public static function getHelp()
    {
        $cates = HelpCate::where('status',1)->where('pid','<>',0)->where('show',1)->take(5)->get();

        return $cates;
    }
}
