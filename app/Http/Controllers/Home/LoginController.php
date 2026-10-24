<?php

namespace App\Http\Controllers\Home;

use App\Models\User;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Support\Facades\Mail;


class LoginController extends Controller
{
    public function getIndex()
    {

        return view('/home/login');
    }
    public function postIndex(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $Vcode = $request->input('Vcode');

        if(!$this->checkVcode($Vcode)){
            return back()->with('error','验证码有误!');
        }


        $user = User::where('username',$username)->first();
        

        if(empty($user)){
            return back()->with('error','无效的用户名!');
        }

        $upass = $user->password;
        if(Hash::check($password,$upass)){
            session(['uid'=>$user->id]);
            session(['pic'=>$user->pic]);
            return redirect('/');
        }
        return back()->with('error','密码错误!');


    }

    public function getRegister()
    {
        return view('home.register');
    }
    public function postRegister(Requests\RegisterRequest $request)
    {
        $Vcode = $request->input('Vcode');

        if(!$this->checkVcode($Vcode)){
            return back()->with('error','验证码有误!');
        }

        $user = new User();
        $data = $request->only('username','password','email','');
        $user->password = Hash::make($data['password']);
        $user->status = 1;
        $user->username = $data['username'];
        $user->email = $data['email'];

        if($user->save()){
            return redirect('/login');
        }else{
            return back()->with('error','添加失败');
        }

    }
    public function getPassword()
    {
        return view('home.password');
    }
    public function postPassword(Request $request)
    {
        $username = $request->input('username');
        $email = $request->input('email');

        $user = User::where('username',$username)->first();

        if(empty($user)){
            return back()->with('error','无效的用户名!');
        }

        $e = $user->email;

//        var_dump(env('MAIL_USERNAME'));
//        var_dump(config('app.APP_NAME'));

//        return view('emails.forget',['user'=> $user]);
        if($email==$e){
            //发邮件
            $request->session()->put('uid',$user->id);
            return redirect('/login/password')->with('mark','1');
            //模态框弹出 a邮箱 跳转login
        }
        return back()->with('error','邮箱不匹配!');
    }
    public function getRepass()
    {
        return view('home.repass');
    }
    public function postRepass(Request $request)
    {
        $data = $request->all();
        $id = session('uid');

        if($data['confirm_password']==$data['password']){

            $user = User::find($id);
            $user->password = Hash::make($data['password']);
            $user->save();
        }
        return redirect('/login');
    }

    /**
     * 校验验证码：忽略大小写与首尾空格；session 里没有验证码时一律判定失败
     */
    private function checkVcode($input)
    {
        $expected = (string) session('Vcode');
        $given = trim((string) $input);

        return $expected !== '' && $given !== '' && strcasecmp($expected, $given) === 0;
    }

    public function getCaptcha()
    {
        // 4 位字符，只用小写字母和数字，并剔除 0/o/1/l/i 等易混淆字符
        $builder = new CaptchaBuilder(null, new PhraseBuilder(4, '23456789abcdefghjkmnpqrstuvwxyz'));

        // 高对比配色：浅底深字（设置了颜色后，库会跳过反色/边缘检测等后期效果）
        $builder->setBackgroundColor(250, 250, 250)
            ->setTextColor(40, 40, 40)
            ->setMaxAngle(4)          // 字符倾斜角度，默认 8
            ->setMaxOffset(2)         // 字符上下偏移，默认 5
            ->setMaxBehindLines(3)    // 文字背后的干扰线
            ->setMaxFrontLines(0)     // 压在文字上的干扰线，最影响辨认，去掉
            ->setDistortion(false);   // 关掉波纹扭曲

        // 尺寸与前台 CSS 保持一致，避免浏览器拉伸放大导致发虚
        $builder->build(125, 46);

        //获取验证码的内容
        $phrase = $builder->getPhrase();

        //把内容存入session
        session()->flash('Vcode', $phrase);

        //生成图片（用 PNG 无损输出；原来 output(false) 会把 JPEG 质量压到最低，字全糊了）
        ob_start();
        imagepng($builder->getGd());
        return response(ob_get_clean(), 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
