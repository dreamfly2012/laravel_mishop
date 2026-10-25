<?php

namespace App\Http\Middleware;

use Closure;

class HomeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(session('uid')){

            return $next($request);

        }

        if(array_key_exists('HTTP_REFERER',$_SERVER)){
            $ref = $_SERVER['HTTP_REFERER'];
            if($this->isInternalUrl($ref)){
                session(['back'=>$ref]);
            }
        }
        // 仅允许站内跳转, 防止伪造 Referer 造成的开放重定向
        $back = session('back');
        if($back && $this->isInternalUrl($back)){
            return redirect($back);
        }
        return redirect('/login');


    }

    /**
     * 判断 URL 是否为本站内部地址 (相对路径, 或 host 等于本站), 防止开放重定向
     */
    private function isInternalUrl($url)
    {
        if(!is_string($url) || $url === ''){
            return false;
        }
        $parsed = parse_url($url);
        if(isset($parsed['scheme']) || isset($parsed['host'])){
            $host = $parsed['host'] ?? '';
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);
            return $host !== '' && $host === $appHost;
        }
        // 没有 host 的视为相对路径, 允许
        return true;
    }

}
