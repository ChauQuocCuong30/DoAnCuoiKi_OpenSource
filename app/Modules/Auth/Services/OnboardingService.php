<?php
declare(strict_types=1);
namespace App\Modules\Auth\Services;
use App\Core\{ApiException,Session};
use PDO;
use PDOException;

final class OnboardingService
{
    private const KEY='onboarding_draft_v1';
    public function __construct(private PDO $db) {}
    public function ready():bool
    {
        try {$this->db->query('SELECT user_id FROM user_onboarding LIMIT 1');return true;}
        catch(PDOException $e){if($e->getCode()==='42S02')return false;throw $e;}
    }
    public function read(?int $user):array
    {
        Session::start();
        if($user===null)return $_SESSION[self::KEY]??[];
        if(!$this->ready())throw new ApiException('UPGRADE_REQUIRED','Cần chạy migration 007_onboarding_survey.sql.',409);
        $q=$this->db->prepare('SELECT answers_json FROM user_onboarding WHERE user_id=?');$q->execute([$user]);$json=$q->fetchColumn();
        return $json===false?[]:json_decode($json,true,32,JSON_THROW_ON_ERROR);
    }
    public function save(?int $user,array $input):array
    {
        $allowed=['reason','level','minutes','start_mode','streak','step','finished','timezone'];
        if(array_diff(array_keys($input),$allowed))throw new ApiException('VALIDATION_FAILED','Khảo sát chứa trường không được hỗ trợ.',422);
        $choices=['reason'=>['free_time','connect','career','fun','study','travel','other'],'level'=>['A1','A2','B1','B2','C1','C2'],'minutes'=>[5,10,15,20],'start_mode'=>['basic','placement'],'streak'=>[7,14,30,50]];
        foreach($choices as $key=>$values)if(array_key_exists($key,$input)&&!in_array($input[$key],$values,true))throw new ApiException('VALIDATION_FAILED','Lựa chọn khảo sát không hợp lệ: '.$key,422);
        if(array_key_exists('step',$input)&&(!is_int($input['step'])||$input['step']<0||$input['step']>7))throw new ApiException('VALIDATION_FAILED','Bước khảo sát không hợp lệ.',422);
        if(array_key_exists('finished',$input)&&!is_bool($input['finished']))throw new ApiException('VALIDATION_FAILED','Trạng thái khảo sát không hợp lệ.',422);
        if(array_key_exists('timezone',$input)&&(!is_string($input['timezone'])||!in_array($input['timezone'],timezone_identifiers_list(),true)))throw new ApiException('VALIDATION_FAILED','Múi giờ không hợp lệ.',422);
        $data=array_replace($this->read($user),$input);
        if($data['finished']??false)foreach(array_keys($choices) as $key)if(!isset($data[$key]))throw new ApiException('VALIDATION_FAILED','Hãy hoàn thành các bước khảo sát trước.',422);
        if($user===null){Session::start();$_SESSION[self::KEY]=$data;}else $this->persist($user,$data);
        return $data;
    }
    private function persist(int $user,array $data):void
    {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$q->execute([$user]);
            if($q->fetchColumn()!=='active')throw new ApiException('FORBIDDEN','Tài khoản không hoạt động.',403);
            $q=$this->db->prepare('INSERT INTO user_onboarding(user_id,answers_json,completed_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE answers_json=VALUES(answers_json),completed_at=VALUES(completed_at)');
            $q->execute([$user,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),($data['finished']??false)?gmdate('Y-m-d H:i:s'):null]);
            $sets=[];$values=[];
            if(isset($data['minutes'])){$sets[]='daily_goal_xp=?';$values[]=[5=>10,10=>20,15=>30,20=>40][$data['minutes']];}
            if(isset($data['streak'])){$sets[]='streak_goal_days=?';$values[]=$data['streak'];}
            if(isset($data['timezone'])){$sets[]='timezone=?';$values[]=$data['timezone'];}
            if($sets){$values[]=$user;$this->db->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?')->execute($values);}
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function attachDraft(int $user):void
    {
        Session::start();$draft=$_SESSION[self::KEY]??null;
        if(!is_array($draft)||!$this->ready())return;
        // Do not overwrite the existing account's preferences with a guest's draft.
        if($this->read($user)===[])$this->save($user,$draft);
        unset($_SESSION[self::KEY]);
    }
}
