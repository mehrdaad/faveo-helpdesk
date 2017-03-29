@extends('themes.default1.admin.layout.admin')

@section('Settings')
active
@stop

@section('settings-bar')
active
@stop

@section('system')
class="active"
@stop

@section('HeadInclude')
@stop
<!-- header -->
@section('PageHeader')
<h1>{{Lang::get('lang.settings')}}</h1>
@stop
<!-- /header -->
<!-- breadcrumbs -->
@section('breadcrumbs')
<ol class="breadcrumb">
</ol>
@stop
<!-- /breadcrumbs -->
<!-- content -->
@section('content')
<!-- open a form -->
{!! Form::model($systems,['url' => 'postsystem/'.$systems->id, 'method' => 'PATCH' , 'id'=>'formID']) !!}
<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">{{Lang::get('lang.system-settings')}}</h3> 
    </div>
    <!-- Helpdesk Status: radio Online Offline -->
    <div class="box-body">
        <!-- check whether success or not -->
        @if(Session::has('success'))
        <div class="alert alert-success alert-dismissable">
            <i class="fa fa-check-circle"></i>
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            {!!Session::get('success')!!}
        </div>
        @endif
        <!-- failure message -->
        @if(Session::has('fails'))
        <div class="alert alert-danger alert-dismissable">
            <i class="fa fa-ban"></i><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <b>{!! Lang::get('lang.alert') !!}!</b><br/>
            <li class="error-message-padding">{!!Session::get('fails')!!}</li>
        </div>
        @endif
        @if(Session::has('errors'))
        <?php //dd($errors); ?>
        <div class="alert alert-danger alert-dismissable">
            <i class="fa fa-ban"></i>
            <b>{!! Lang::get('lang.alert') !!}!</b>
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <br/>
            @if($errors->first('user_name'))
            <li class="error-message-padding">{!! $errors->first('user_name', ':message') !!}</li>
            @endif
            @if($errors->first('first_name'))
            <li class="error-message-padding">{!! $errors->first('first_name', ':message') !!}</li>
            @endif
            @if($errors->first('last_name'))
            <li class="error-message-padding">{!! $errors->first('last_name', ':message') !!}</li>
            @endif
            @if($errors->first('email'))
            <li class="error-message-padding">{!! $errors->first('email', ':message') !!}</li>
            @endif
        </div>
        @endif
        <div class="row">
           
            <!-- Helpdesk Name/Title: text Required   -->
            <div class="col-md-4">
                <div class="form-group {{ $errors->has('name') ? 'has-error' : '' }}">
                    {!! Form::label('name',Lang::get('lang.name/title')) !!}
                    {!! $errors->first('name', '<spam class="help-block">:message</spam>') !!}
                    {!! Form::text('name',$systems->name,['class' => 'form-control']) !!}
                </div>
            </div>
             <!-- Helpdesk URL:      text   Required -->
             <div class="col-md-4">
                <div class="form-group {{ $errors->has('url') ? 'has-error' : '' }}">
                    {!! Form::label('url',Lang::get('lang.url')) !!}
                    {!! $errors->first('url', '<spam class="help-block">:message</spam>') !!}
                    {!! Form::text('url',$systems->url,['class' => 'form-control']) !!}
                </div>
            </div>
            <!-- Default Time Zone: Drop down: timezones table : Required -->
            <div class="col-md-4">
                <div class="form-group {{ $errors->has('time_zone') ? 'has-error' : '' }}">
                    {!! Form::label('time_zone',Lang::get('lang.timezone')) !!}
                    {!! $errors->first('time_zone', '<spam class="help-block">:message</spam>') !!}
                    {!!Form::select('time_zone',['Time Zones'=>$timezones],null,['class'=>'form-control','id'=>'tz']) !!}
                </div>
            </div>
        </div>
        <div class="row">
            <!-- Date and Time Format: text: required: eg - 03/25/2015 7:14 am -->
            <div class="col-md-4">
                <div class="form-group {{ $errors->has('date_time_format') ? 'has-error' : '' }}">
                    {!! Form::label('date_time_format',Lang::get('lang.date_time')) !!}
                    {!! $errors->first('date_time_format', '<spam class="help-block">:message</spam>') !!}
                    {!! Form::select('date_time_format',['Date Time Formats'=>$date_time->pluck('format','id')->toArray()],null,['class' => 'form-control']) !!}
                </div>
            </div>
           
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('status',Lang::get('lang.status')) !!}
                    <div class="row">
                        <div class="col-xs-5">
                            {!! Form::radio('status','1',true) !!} {{Lang::get('lang.online')}}
                        </div>
                        <div class="col-xs-6">
                            {!! Form::radio('status','0') !!} {{Lang::get('lang.offline')}}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('user_set_ticket_status',Lang::get('lang.user_set_ticket_status')) !!}
                    <div class="row">
                        <div class="col-xs-5">
                            <input type="radio" name="user_set_ticket_status" value="0" @if($common_setting->status == '0')checked="true" @endif>&nbsp;{{Lang::get('lang.no')}}
                        </div>
                        <div class="col-xs-6">
                            <input type="radio" name="user_set_ticket_status" value="1" @if($common_setting->status == '1')checked="true" @endif>&nbsp;{{Lang::get('lang.yes')}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">    
            <div class="col-md-4" data-toggle="tooltip" title="{!! Lang::get('lang.the_rtl_support_is_only_applicable_to_the_outgoing_mails') !!}">
                <div class="form-group">
                    {!! Form::label('status',Lang::get('lang.rtl')) !!}
                    <div class="row">
                        <div class="col-xs-12">
                            <?php
                            $rtl = App\Model\helpdesk\Settings\CommonSettings::where('option_name', '=', 'enable_rtl')->first();
                            ?>
                            <input type="checkbox" name="enable_rtl" @if($rtl->option_value == 1) checked @endif> {{Lang::get('lang.enable')}}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-toggle="tooltip" title="{!! Lang::get('lang.otp_usage_info') !!}">
                <div class="form-group">
                    {!! Form::label('send_otp',Lang::get('lang.allow_unverified_users_to_create_ticket')) !!}
                    <div class="row">
                        <div class="col-xs-5">
                            <input type="radio" name="send_otp" value="0" @if($send_otp->status == '0')checked="true" @endif>&nbsp;{{Lang::get('lang.yes')}}
                        </div>
                        <div class="col-xs-6">
                            <input type="radio" name="send_otp" value="1" @if($send_otp->status == '1')checked="true" @endif>&nbsp;{{Lang::get('lang.no')}}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-toggle="tooltip" title="{!! Lang::get('lang.email_man_info') !!}">
                <div class="form-group">
                    {!! Form::label('email_mandatory',Lang::get('lang.make-email-mandatroy')) !!}
                    <div class="row">
                        <div class="col-xs-5">
                            <input type="radio" name="email_mandatory" value="1" @if($email_mandatory->status == '1')checked="true" @endif>&nbsp;{{Lang::get('lang.yes')}}
                        </div>
                        <div class="col-xs-6">
                            <input type="radio" name="email_mandatory" value="0" @if($email_mandatory->status == '0')checked="true" @endif>&nbsp;{{Lang::get('lang.no')}}
                        </div>
                    </div>
                </div>
            </div>
            
            
        </div>
        <?php
            $custom_format = "";
            $date_time_format = "";
            if(!in_array($systems->date_time_format,$formats)){
               $custom_format =  $systems->date_time_format;
            }
            if(!in_array($systems->date_time_format,$formats)){
               $date_time_format =  'custom';
            }else{
                $date_time_format = $systems->date_time_format;
            }
            ?>
        <div class="row">
            <!-- Date and Time Format: text: required: eg - 03/25/2015 7:14 am -->
            <div class="col-md-6">
                <div class="form-group {{ $errors->has('date_time_format') ? 'has-error' : '' }}">
                    <span>{!! Form::label('date_time_format',Lang::get('lang.date_time')) !!}(<i id="dateformat"></i>)</span>
                    {!! $errors->first('date_time_format', '<spam class="help-block">:message</spam>') !!}
                    {!! Form::select('date_time_format',['Date Time Formats'=>$formats],$date_time_format,['class' => 'form-control','id'=>'format']) !!}
                </div>
            </div>
            
            <div class="col-md-6" id="custom" @if(in_array($systems->date_time_format,$formats))style="display: none;"@endif>
                <div class="form-group {{ $errors->has('custom-format') ? 'has-error' : '' }}">
                    {!! Form::label('custom-format',Lang::get('lang.custom-format')) !!}
                    {!! $errors->first('custom-format', '<spam class="help-block">:message</spam>') !!}
                    {!! Form::text('custom-format',$custom_format,['class' => 'form-control','id'=>'custom-format']) !!}
                </div>
            </div>
            
        </div>
    </div>
    <div class="box-footer">
        {!! Form::submit(Lang::get('lang.submit'),['onclick'=>'sendForm()','class'=>'btn btn-primary'])!!}
    </div>
</div>

@stop
@push('scripts')
<script>
    $(document).ready(function(){
        var format = $("#format").val();
        var custom_format = $("#custom-format").val();
        if(custom_format){
           format =  custom_format;
        }
        var tz = $("#tz").val();
        date(format,tz);
    });
    $("#tz").on('change',function(){
        var format = $("#format").val();
        if(format=='custom'){
            format = $("#custom-format").val();
        }
        var tz = $("#tz").val();
        date(format,tz);
    });
    $("#format").on('change',function(){
        var format = $("#format").val();
        if(format!=='custom'){
            $("#custom").hide();
            var tz = $("#tz").val();
            date(format,tz);
        }else{
            $("#custom").show();
        }
        
    });
    $("#custom-format").on('input',function(){
        var format = $("#custom-format").val();
            var tz = $("#tz").val();
            date(format,tz); 
    });
    function date(format,tz){
        $.ajax({
            url:"{{url('date/get')}}",
            dataType:"html",
            data : "format="+format+"&tz="+tz,
            success:function(date){
                $("#dateformat").html(date);
            }
        });
        
    }
</script>
@endpush
