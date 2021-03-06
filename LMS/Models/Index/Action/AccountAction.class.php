<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/5 0005
 * Time: 下午 9:07
 */
class AccountAction extends CommonAction
{
    /**
     * 主页！
     */
    function index(){
        $this->display();
    }

    /**
     * 修改头像的界面
     */
    function face(){
        $this->initUniqid(GROUP_NAME.'/Account/faceHandle');
        $this->display();
    }

    /**
     * 修改头像的提交！
     */
    public function faceHandle(){
        if(!IS_POST){
            _404('页面不存在！');
        }
        if(!checkFormUniqid(I('post.uniqid'))){
            $this->error('表单标识码不匹配，为了您的安全，请刷新后重试！');
        }
        $uid=session('uid');
        $db=M('user');

        load('@/account');
        $face = save_user_image($uid,249,249,'m_');
        if($face['status'])
            $face = get_thumb_file($face['path'],'m_');
        else{
            $this->ajaxReturn($face);
        }

        $arr=array(
            'id'    =>  $uid,
            'face'  =>  $face,
        );
        if(!$db->save($arr)){
            $this->error('保存失败，请重试！');
        }else{
            clear_cache('user/'.$uid.'/',true);//清除所有缓存，否则由于缓存容易出现头像，用户名显示错误。
            unlink($this->user['face']);//删除之前的头像。
            clearUniqid();
            $this->redirect(GROUP_NAME.'/Account/index');
        }
    }
    function basic(){
        $uid=session('uid');
        $db=M('user');
        $user=$db->field('id,username,info,sex,birth')->find($uid);
        $this->data=$user;

        $this->initUniqid(GROUP_NAME.'/Account/basicHandle');
        $this->display();
    }
    function basicHandle(){
        if(!IS_POST)
            _404('页面不存在！');

        $this->checkFormUniqid(I('post.uniqid'));

        $uid=session('uid');
        $username=I('post.username');
        $sex=intval(I('post.sex'))%2;
        $info=I('post.info');
        $info=empty($info)?'他（她）没有留下自我介绍哦!':$info;
        $birth=strtotime(I('post.birth'));
        $db=M('user');

        load('@/check');

        $str=mb_check_stringLen($username,C('MIN_NAME'),C('MAX_NAME'),'用户名');
        if(!$str->isValid())
            $this->error($str->getMessage());
        // /u是utf8的意思。
        ///^[\u4E00-\u9FFFa-zA-z1-9_]*$/这个用在js里比较好。
        if(!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-z1-9_]*$/u',$username))
            $this->error('用户名只能是中文、英文、数字、下划线');
        $str=mb_check_stringLen($info,0,200,'自我介绍');
        if(!$str->isValid())
            $this->error($str->getMessage());


        if($birth>time())
            $this->error('生日不能在今天之后！');
        if(!checkRepeatUsername($username,$uid)){
            $this->error('用户名重复！');
        }

        //清理标识码
        clearUniqid();
        $data=array(
            'id'        =>  $uid,
            'username'  =>  $username,
            'sex'       =>  $sex,
            'info'      =>  $info,
            'birth'     =>  $birth,
        );
        if($db->save($data)) {
            clear_cache('user/'.$uid.'/',true);//清除所有缓存，否则由于缓存容易出现头像，用户名显示错误。
            $this->success('修改成功！', U(GROUP_NAME . '/Account/index'));
        }
        else{
            $this->error('没有数据被修改！');
        }
    }
    function person(){
        $uid=session('uid');
        $db=M('user_config');

        //处理异常
        $map=array(
            'uid'   =>  array('eq',$uid),
        );
        $info=$db->where($map)->limit(1)->select();
        $this->data=$info[0];

        //p($this->data);

        $this->initUniqid(GROUP_NAME.'/Account/personHandle');
        $this->display();
    }
    function personHandle(){
        if(!IS_POST)
            _404('页面不存在！');
        //检查唯一标识码！
        $this->checkFormUniqid(I('post.uniqid'));

        $uid=session('uid');
        $db=M('user_config');

        //这个时间戳是今天那个时间点的时间戳
        $evd_time=strtotime(date('Y-m-d ',time()).$_POST['rem_evd_time']);
        if($evd_time<time())
            $evd_time+=86400;//如果在现在的时间之前，就加一天，避免重复发送。
        $warn_time=strtotime(date('Y-m-d ',time()).$_POST['rem_warn_time']);
        if($warn_time<time())
            $warn_time+=86400;//如果在现在的时间之前，就加一天，避免重复发送。
        //echo date('Y-m-d ',time()).$_POST['rem_warn_time'].'<br>'.date('Y-m-d H:i:s',$warn_time);

        clearUniqid();
        $data=array(
            //'stu_time'      =>  intval(I('post.stu_time'))%16,
            'fri_rej_all'   =>  intval(I('post.fri_rej_all'))%2,
            'rem_message'   =>  intval(I('post.rem_message'))%2,
            'rem_evd_time'  =>  $evd_time,
            'rem_warn_time' =>  $warn_time,
            'rem_evd'       =>  intval(I('post.rem_evd'))%2,
            'rem_warn'      =>  intval(I('post.rem_warn'))%2,
        );

        if(!$db->where("uid='%d'",$uid)->limit(1)->save($data)){
            //p($db->getLastSql());
            $this->error('没有任何数据被修改，请重试！');
        }
        $user=M('user')->field('email')->find($uid);
        load('@/email');
        //如果开启每日提醒，重设重复邮件
        delEmailTimeQueue('lms_evd+'.$uid,null);
        if($data['rem_evd']){
            addEmailTimeQueue($user['email'],'每日提醒邮件',C('WEB_NAME'),'get_uncomplete_plan',$evd_time,'lms_evd+'.$uid,1,true);
        }
        //如果开启每日警告，重设重复邮件
        delEmailTimeQueue('lms_warn+'.$uid,null);
        if($data['rem_warn']){
            addEmailTimeQueue($user['email'],'每日警告邮件',C('WEB_NAME'),'get_uncomplete_plan',$warn_time,'lms_warn+'.$uid,1,true);
        }
        $this->success('修改成功！',U(GROUP_NAME.'/Account/index'));
    }

    /**
     * 密码第一步的界面
     */
    function password(){
        $this->initUniqid(GROUP_NAME.'/Account/passwordHandle');
        $this->display();
    }

    /**
     * 密码第一步的提交和第二步的页面！
     */
    function passwordHandle(){
        if(!IS_POST){
            _404('页面不存在！');
           /* //虽然用户能直接进入此页面，但是有邮箱验证码在做防护！
            $this->initUniqid(GROUP_NAME.'/Account/passwordSetNew');
            //如果不是提交，那么就显示页面，这里不用对用户做过多的限制，比如只能一次性进入页面，因为有一次性邮箱验证码在！
            $this->display();
            die();*/
        }
        if(!checkFormUniqid(I('post.uniqid'))){
            $this->error('表单唯一标识码不匹配，为了您的安全，请刷新重试！',U(GROUP_NAME.'/Account/password'));
        }
        $uid=session('uid');
        $db=M('user');
        $user=$db->field(array('id','password','email'))->find($uid);

        $password=calc_password(I('post.password'));

        if($password==$user['password']){
            load('@/code');
            //创建一个存储于数据库的code码
            $code=createCode();

            sendCode($user['email'],$code);

            //在数据库中存储passwordSetNew的验证码
            saveCode($uid,$code,GROUP_NAME.'/Account/passwordSetNew');
            //这里之所以没有清理上个表单的唯一标识码，就是因为用户可能刷新页面重新获取。
            //$this->redirect(GROUP_NAME.'/Account/passwordHandle');
			$this->display();
        }else{
            $this->error('原密码输入错误');
        }
    }

    /**
     * 密码第二步的提交
     */
    function passwordSetNew()
    {
        if (!IS_POST) {
            _404('页面不存在');
        }
        if (!checkFormUniqid(I('post.uniqid'))) {
            $this->error('表单唯一标识码不匹配，为了您的安全，请刷新重试！');
        }
        $pwd=I('post.password');
        $pwd_2=I('post.password_2');

        if($pwd!=$pwd_2)
            $this->error('两次输入密码不匹配!');

        //为了确保安全，这里的标识码只能用一次，每次改密码的时候才会生成这个标识码！

        load('@/check');
        //加密后不能验证密码长度。
        /*if($str=mb_check_stringLen($pwd,C('MIN_PAS'),C('MAX_PAS'),'密码长度')!=true){
            $this->error($str);
        }*/
        load('@/code');
        $uid=session('uid');
        $db=M('user');

        switch(checkCode($uid,I('post.code'))){
            case 0:
                clearUniqid();
                $arr=array(
                    'id'    =>  $uid,
                    'password'  =>  calc_password($pwd),
                );
                if($db->save($arr)){
                    clearCode($uid);
                    logout();
                    $this->success('修改成功，请重新登录！',U(GROUP_NAME.'/Login/index'));
                }else{
                    $this->error('没有任何数据被修改',U(GROUP_NAME.'/Account/password'));
                }
                break;
            case 1:
                $this->error('密匙输入错误，请重新输入！');
                add_log("密匙不存在？",true);
                break;
            case 2:
                clearUniqid();
                $this->error('密匙已失效，请重新请求！',U(GROUP_NAME.'/Account/password'));
                break;
            default:
                break;
        }
    }

    /**
     * 检查邮箱验证码
     */
    function CheckCode(){
        if(!IS_POST||!IS_AJAX){
            _404('页面不存在！');
        }
        $uid=session('uid');
        $code=I('post.code');
        $type=I('get.type');

        $arr=array('valid'=>false);
        load('@/code');
        $control=GROUP_NAME.'/Account/';

        //为相应类型计算出控制器。
        if($type=='password')
            $control.='passwordSetNew';
        elseif($type=='originEmail')
            $control.='mailHandle';
        elseif($type='newEmail')
            $control=I('post.mail');
        else
            $control=null;

        //检查码是否正确
        if(checkCode($uid,$code,$control,false)==0){
            $arr['valid']=true;
        }
        /*$arr['lastSql']=M('code')->getLastSql();
        $arr['code']=checkCode($uid,$code,$control,false);*/
        $this->ajaxReturn($arr);
    }

    /**\
     * 第一步的页面
     */
    function mail(){
        $uid=session('uid');
        $user=M('user')->field('email')->find($uid);
        $this->email=$user['email'];

        $this->initUniqid(GROUP_NAME.'/Account/mailHandle');

        $this->display();
    }

    /**
     * 第一步的提交结果和第二步的页面！
     */
    function mailHandle(){
        if(!IS_POST){
            _404('页面不存在！');
        }
        if(!checkFormUniqid(I('post.uniqid'))){
            $this->error('表单标识码不正确，请刷新重试！');
        }
        $uid=session('uid');
        $code=I('post.code');
        load('@/code');
        //在这里先不删除code，邮箱更改完成后删除key
        switch(checkCode($uid,$code,null,false)){
            case 0:
                $this->initUniqid(GROUP_NAME.'/Account/mailSetNew');
                $this->display();
                break;
            case 1:
                $this->error('密匙不正确！');
                break;
            case 2:
                $this->error('密匙已过期');
                break;
            default:
                break;
        }
    }

    /**
     * 设置新的邮箱，表单提交。
     */
    function mailSetNew(){
        if(!IS_POST){
            _404('页面不存在！');
        }
        if(!checkFormUniqid(I('post.uniqid'))){
            $this->error('表单验证码失效，为了您的安全，请刷新重试！');
        }
        $uid=session('uid');
        $email=I('post.mail');
        $code=I('post.code');
        $db=M('user');
        $user=$db->field('id,email')->find($uid);

        if($user['email']==$email)
            $this->error('更改失败，不能和前邮箱相同！');

        load('@/code');
        switch(checkCode($uid,$code,$email)){
            case 0:
                //deleteCode($uid,GROUP_NAME.'/Account/mailHandle');
                clearCode($uid);
                $user['email']=$email;
                if($db->save($user)){
                    logout();
                    $this->success('修改成功，请重新登录！',U(GROUP_NAME.'/Login/index'));
                }
                break;
            case 1:
                $this->error('密匙不正确');
                break;
            case 2:
                $this->error('密匙已过期，请重新请求');
                break;
            default:
                $this->redirect(GROUP_NAME.'/Account/index');
                break;
        }
    }

    /**
     * 发送设置新邮箱的邮箱验证码！
     */
    function sendNewEmailCode(){
        if(!IS_POST||!IS_AJAX){
            _404('页面不存在！');
        }

        $data=array(
            'status'    =>  false,
            'text'      =>  '',
        );
        $uid=session('uid');
        $email=I('post.mail');
        $user=M('user')->field('id,email')->find($uid);
        if($email==$user['email']){
            $data['text']='不能和当前邮箱重复！';
            $this->ajaxReturn($data);
        }

        load('@/code');
        $code=createCode();
        saveCode($uid,$code,$email);
        sendCode($email,$code);
        $data['status']=true;
        $this->ajaxReturn($data);
    }
    function sendEmailCode(){
        if(!IS_POST||!IS_AJAX){
            _404('页面不存在！');
        }

        $data=array(
            'status'    =>  false,
            'text'      =>  '',
        );
        //匹配相关控制器
        $control=GROUP_NAME.'/Account/';
        $type=I('get.type');

        //为相应的类型创建不同的控制器类型
        if($type=='originEmail')
            $control.='mailHandle';
        elseif($type='newEmail')
            $control.='mailSetNew';
        else
            $control=null;


        if(!checkUrlUniqid(I('get.uniqid'),$control)){
            $data['text']='表单标识码不正确，请刷新重试！';
            $this->ajaxReturn($data);
        }

        $uid=session('uid');
        $user=M('user')->field(array('id','email'))->find($uid);

        load('@/code');
        $code=createCode(C('ACCOUNT_CODE_LEN'));
        saveCode($uid,$code,$control);
        sendCode($user['email'],$code);

        $data['status']=true;
        $this->ajaxReturn($data);
    }
}