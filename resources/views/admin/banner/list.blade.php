@extends('layout.admin')
@section('title','首页展示位管理')
@section('content')
<div class="mws-panel grid_8 mws-collapsible">
    <div class="mws-panel-header">
        <span> <i class="icon-table"></i>
            首页展示位管理 (轮播图 / 促销小图 / 频道入口)
        </span>
        <div class="mws-collapse-button mws-inset">
            <span></span>
        </div>
    </div>
    <div class="mws-panel-inner-wrap">
        <div class="mws-panel-body no-padding">
            <div id="DataTables_Table_0_wrapper" class="dataTables_wrapper" role="grid">
                <div class="dataTables_filter" id="DataTables_Table_0_filter" style="padding:8px 10px;">
                    <form action="/admin/banner/list" method="get" style="display:inline-block;">
                        <label>展示位:
                            <select name="position" onchange="this.form.submit()">
                                <option value="">全部</option>
                                @foreach($positions as $key=>$label)
                                <option value="{{$key}}" @if($position==$key) selected="selected" @endif>{{$label}}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                    <a href="/admin/banner/add" class="btn btn-primary" style="margin-left:10px;">+ 新增展示位</a>
                </div>
                <table class="mws-table mws-datatable dataTable" id="DataTables_Table_0" aria-describedby="DataTables_Table_0_info">
                    <thead>
                        <tr role="row">
                            <th>ID</th>
                            <th>展示位</th>
                            <th>标题</th>
                            <th>图片 / 图标</th>
                            <th>关联商品ID</th>
                            <th>自定义链接</th>
                            <th>排序</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody role="alert" aria-live="polite" aria-relevant="all">
                        @forelse($banners as $v)
                        <tr class="">
                            <td>{{$v->id}}</td>
                            <td>{{$positions[$v->position] ?? $v->position}}</td>
                            <td>{{$v->title}}</td>
                            <td>
                                @if($v->image)
                                    <img src="{{$v->image}}" style="max-height:40px;" alt="">
                                @elseif($v->icon)
                                    <i class="iconfont">{!! $v->icon !!}</i> (图标)
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{$v->good_id ?? '-'}}</td>
                            <td>{{$v->url ?? '-'}}</td>
                            <td>{{$v->sort}}</td>
                            <td><input class="ibutton" cid="{{$v->id}}" type="checkbox" {{$v->status? 'checked':''}}></td>
                            <td>
                                <span class="btn-group">
                                    <a href="/admin/banner/edit/{{$v->id}}" class="btn btn-small">
                                        <i class="icon-pencil"></i>
                                    </a>
                                    <a href="/admin/banner/delete/{{$v->id}}" class="btn btn-small" onclick="return confirm('确认删除?');">
                                        <i class="icon-trash"></i>
                                    </a>
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" style="text-align:center;">暂无数据</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@section('css')
<link rel="stylesheet" type="text/css" href="/back/plugins/ibutton/jquery.ibutton.css" media="screen">
<link rel="stylesheet" type="text/css" href="/back/css/mws-style.css" media="screen">
@endsection
@section('js')
<script src="/back/js/libs/jquery-1.8.3.min.js"></script>
<script src="/back/plugins/ibutton/jquery.ibutton.min.js"></script>
<script src="/back/js/core/mws.js"></script>
<script>
$(function(){
    $('.ibutton-container').on('click',function(){
        if($(this).data('ck'))return;
        $(this).data('ck',1);
        var $id = $(this).find('.ibutton').attr('cid');
        var status = $(this).find('.ibutton').prop('checked');
        status = status?1:0;
        var $self = $(this);
        $.ajax({
            url:'/admin/banner/update/'+$id,
            type:'GET',
            data:{status:status},
            success: function(data){
                if( data==='0' ){
                     $self.data('ck',false);
                }else{
                    $self.find('.ibutton').prop('checked',false);
                    $self.data('ck',false);
                    alert('状态更改失败');
                }
            }
        });
    });
});
</script>
@endsection
