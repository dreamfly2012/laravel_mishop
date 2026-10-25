<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Good;
use App\Support\ImageUpload;

class BannerController extends Controller
{
    // 三个展示位: 轮播图 / 焦点图下方促销小图 / 频道入口
    protected const POSITIONS = [
        'carousel' => '首页轮播图',
        'promo'    => '焦点图下方促销小图',
        'channel'  => '首页频道入口',
    ];

    /**
     * 列表页 (可按 position 筛选)
     */
    public function getList(Request $request)
    {
        $position = $request->input('position');
        $query = Banner::query();

        if ($position && isset(self::POSITIONS[$position])) {
            $query->where('position', $position);
        }

        $banners = $query->orderBy('position')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return view('admin.banner.list', [
            'banners'  => $banners,
            'position' => $position,
            'positions' => self::POSITIONS,
        ]);
    }

    /**
     * 添加页
     */
    public function getAdd(Request $request)
    {
        $goods = Good::where('status', 1)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'title', 'price', 'showImg']);

        $position = $request->input('position', 'carousel');

        return view('admin.banner.add', [
            'goods'    => $goods,
            'position' => $position,
            'positions' => self::POSITIONS,
        ]);
    }

    /**
     * 执行添加
     */
    public function postAdd(Request $request)
    {
        $position = $request->input('position', 'carousel');
        if (!isset(self::POSITIONS[$position])) {
            return back()->with('error', '未知的展示位');
        }

        $image = $this->resolveImage($request);

        $banner = new Banner;
        $banner->position = $position;
        $banner->title    = $request->input('title');
        $banner->image    = $image;
        $banner->icon     = $request->input('icon');
        $banner->good_id  = $request->input('good_id') ? (int) $request->input('good_id') : null;
        $banner->url      = $request->input('url');
        $banner->sort     = (int) $request->input('sort', 0);
        $banner->status   = $request->input('status') ? 1 : 0;

        if ($banner->save()) {
            return redirect('/admin/banner/list?position=' . $position)->with('info', '添加成功');
        }

        return back()->with('error', '添加失败');
    }

    /**
     * 编辑页
     */
    public function getEdit(Request $request)
    {
        $banner = Banner::findOrFail($request->route('id'));

        $goods = Good::where('status', 1)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'title', 'price', 'showImg']);

        return view('admin.banner.edit', [
            'banner'    => $banner,
            'goods'     => $goods,
            'positions' => self::POSITIONS,
        ]);
    }

    /**
     * 执行编辑
     */
    public function postEdit(Request $request)
    {
        $banner = Banner::findOrFail($request->input('id'));

        $position = $request->input('position', $banner->position);
        if (!isset(self::POSITIONS[$position])) {
            return back()->with('error', '未知的展示位');
        }

        $image = $this->resolveImage($request, $banner->image);

        $banner->position = $position;
        $banner->title    = $request->input('title');
        $banner->image    = $image;
        $banner->icon     = $request->input('icon');
        $banner->good_id  = $request->input('good_id') ? (int) $request->input('good_id') : null;
        $banner->url      = $request->input('url');
        $banner->sort     = (int) $request->input('sort', 0);
        $banner->status   = $request->input('status') ? 1 : 0;

        if ($banner->save()) {
            return redirect('/admin/banner/list?position=' . $position)->with('info', '修改成功');
        }

        return back()->with('error', '修改失败');
    }

    /**
     * 删除
     */
    public function getDelete(Request $request)
    {
        $banner = Banner::findOrFail($request->route('id'));

        if ($banner->delete()) {
            return back()->with('info', '删除成功');
        }

        return back()->with('error', '删除失败');
    }

    /**
     * ajax 切换状态
     */
    public function getUpdate(Request $request)
    {
        $banner = Banner::findOrFail($request->route('id'));
        $banner->status = $request->input('status') ? 1 : 0;

        return $banner->save() ? response('0') : response('1');
    }

    /**
     * 解析图片: 优先上传文件, 其次使用表单里的图片路径文本
     */
    private function resolveImage(Request $request, ?string $default = null): ?string
    {
        if ($request->hasFile('image_file')) {
            return ImageUpload::store($request, 'image_file');
        }

        $path = trim((string) $request->input('image', ''));
        if ($path !== '') {
            return $path;
        }

        return $default;
    }
}
