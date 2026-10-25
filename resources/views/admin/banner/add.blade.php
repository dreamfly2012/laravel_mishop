@extends('layout.admin')
@section('title','新增首页展示位')
@section('content')
<div class="mws-panel grid_8">
    <div class="mws-panel-header">
        <span><i class="icon-plus"></i> 新增首页展示位</span>
    </div>
    <div class="mws-panel-body no-padding">
        @if($errors->any())
        <div class="mws-form-message error">
            错误信息提示:
            <ul>
                @foreach($errors->all() as $k=>$v)
                <li>{{$v}}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <form id="banner" class="mws-form" action="/admin/banner/add" method="post" enctype="multipart/form-data">
            <div class="mws-form-inline">
                <div class="mws-form-row">
                    <label class="mws-form-label">展示位</label>
                    <div class="mws-form-item">
                        <select name="position" class="large">
                            @foreach($positions as $key=>$label)
                            <option value="{{$key}}" @if($position==$key) selected="selected" @endif>{{$label}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mws-form-row">
                    <label class="mws-form-label">标题</label>
                    <div class="mws-form-item">
                        <input type="text" class="large" name="title" value="{{old('title')}}">
                    </div>
                </div>
                <div class="mws-form-row">
                    <label class="mws-form-label">图片(轮播/促销)</label>
                    <div class="mws-form-item">
                        <input type="file" name="image_file">
                        <p class="tips" style="color:#888;">也可直接填写图片路径:</p>
                        <input type="text" class="large" name="image" placeholder="/image/upload/mi-xxx.jpg 或 /cdn/...">
                    </div>
                </div>
                <div class="mws-form-row">
                    <label class="mws-form-label">图标(频道入口)</label>
                    <div class="mws-form-item">
                        <input type="text" class="large" name="icon" placeholder="iconfont 字符, 如 &#xe640;">
                    </div>
                </div>
                <div class="mws-form-row">
                    <label class="mws-form-label">关联商品(点击跳转)</label>
                    <div class="mws-form-item">
                        <select name="good_id" class="large">
                            <option value="">不关联</option>
                            @foreach($goods as $g)
                            <option value="{{$g->id}}">[{{$g->id}}] {{$g->title}} - ￥{{$g->price}}</option>
                            @endforeach
                        </select>
                        <p class="tips" style="color:#888;">关联后点击展示位跳转到 /detail/{商品ID}</p>
                    </div>
                </div>
                <div class="mws-form-row">
                    <label class="mws-form-label">自定义链接</label>
                    <div class="mws-form-item">
                        <input type="text" class="large" name="url" placeholder="/list/1 或 https://...">
                    </div>
                </div>
                <div class="mws-form-row">
                    <label class="mws-form-label">排序</label>
                    <div class="mws-form-item">
                        <input type="text" class="large" name="sort" value="0">
                    </div>
                </div>
                <div class="mws-form-row">
                    <label class="mws-form-label">状态</label>
                    <div class="mws-form-item clearfix">
                        <ul class="mws-form-list inline">
                            <li><input type="radio" name="status" value="1" checked> <label>启用</label></li>
                            <li><input type="radio" name="status" value="0"> <label>禁用</label></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mws-button-row">
                {{csrf_field()}}
                <input type="submit" value="提交" class="btn btn-danger">
                <input type="reset" value="重置" class="btn ">
            </div>
        </form>
    </div>
</div>
@endsection
