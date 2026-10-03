<?php
declare(strict_types=1);
require __DIR__.'/Fixtures.php';
use OneId\Tests\MobileOidc\Fixture;
function profile(Fixture $f,string $number):array{
 $id=$f->begin();$r=$f->password($id,$number);
 if(($r['code']??'')==='MFA_REQUIRED'){$f->send($id);$f->verify($id,'email',$f->delivery->otp);}
 $f->finish($id);return $f->status($f->provider->accepted[0]);
}
function verify(bool $v,string $label):void {if(!$v)throw new RuntimeException($label);echo "PASS $label\n";}
$f=new Fixture();$f->source->rows['STAFF_FIXTURE']['data4']='900101010101';$r=profile($f,'0530-09');
verify($r['staff_number']==='0530-09' && $r['staff_number_short']==='0530','both staff formats preserve leading zero');
verify($r['full_name']==='Synthetic identity' && $r['email']==='fixture@example.invalid' && $r['department']==='Synthetic department' && $r['job_title']==='Synthetic position','staff profile maps source fields');
verify($r['student_matric_number']===null && !str_contains(json_encode($r),'900101010101'),'staff NRIC is never exposed as matric');
$f=new Fixture();$f->source->rows['STUDENT_FIXTURE']['data2']='900101010101';$f->source->rows['STUDENT_FIXTURE']['data7']='Program Contoh';$r=profile($f,'M123456');
verify($r['student_matric_number']==='M123456' && $r['staff_number']===null && $r['staff_number_short']===null,'student returns matric only');
verify($r['job_title']==='Program Contoh' && !str_contains(json_encode($r),'900101010101'),'student job_title returns program without exposing NRIC');
$f=new Fixture();$f->source->rows['STAFF_FIXTURE']['data6']='';$f->source->rows['STAFF_FIXTURE']['data7']=null;$r=profile($f,'0530-09');
verify($r['department']===null && $r['job_title']===null,'missing source fields return null');

$f=new Fixture();$f->source->rows['STUDENT_FIXTURE']['data7']='  ';$r=profile($f,'M123456');
verify($r['job_title']===null,'missing student program returns null');
