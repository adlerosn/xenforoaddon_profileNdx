<?php
class ProfileNdx_search_engine
{
	public static function mysql_escape_mimic_fromPhpDoc($inp){
		return ProfileNdx_indexer_shared::mysql_escape_mimic_fromPhpDoc($inp);
	}

	public static function createSearchCacheTable(){
		$q="CREATE TABLE IF NOT EXISTS `kiror_profile_search_cache` (
		q VARCHAR(255) PRIMARY KEY,
		d INTEGER,
		r LONGBLOB
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}

	public static function createSearchLimitsTable(){
		$q="CREATE TABLE IF NOT EXISTS `kiror_profile_search_limiting` (
		s SERIAL,
		q VARCHAR(255),
		d INTEGER,
		u INTEGER
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}

	public static function deleteSearchCacheTable(){
		$q="DROP TABLE IF EXISTS `kiror_profile_search_cache`;";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}

	public static function deleteSearchLimitTable(){
		$q="DROP TABLE IF EXISTS `kiror_profile_search_limiting`;";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}

	private function repeatingTimes($haystack, $atomicNeedle)
	{
		if(count_chars($haystack)==0) return 0;
		if(count_chars($atomicNeedle)==0) return 0;
		return count(explode(mb_strtolower($atomicNeedle,'UTF-8'),mb_strtolower($haystack,'UTF-8')))-1;
	}	//because only an atomicNeedle can make a haystack explode! :p

	private function plainifyArrayOfStrings($inp){
		if(is_array($inp)){
			$s='';
			foreach($inp as $v){
				$s.=' '.$this->plainifyArrayOfStrings($v).' ';
			}
			return $s;
		}
		else return (string)$inp;
	}
	
	private function filterSearchInput($rp){
		$su=ProfileNdx_indexer_shared::searchableUsers();
		$passed=array();
		foreach($rp as $k=>$v){
			if(array_key_exists($k,$su)) {
				$passed[$k]=$v;
			}
		}
		return $passed;
	}

	public static function cleanOldsFromCache(){
		$acc=time();
		$xfopt=XenForo_Application::get('options');
		$searchlifetime=$xfopt->searchlifetime;
		$lim=$acc-$searchlifetime;
		$q="DELETE FROM `kiror_profile_search_cache` WHERE d<".$lim.";";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}
	
	public static function cleanOldsFromLimit(){
		$acc=time();
		$xfopt=XenForo_Application::get('options');
		$searchblocktime=$xfopt->searchblocktime;
		$lim=$acc-$searchblocktime;
		$q="DELETE FROM `kiror_profile_search_limiting` WHERE d<".$lim.";";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}
	
	public static function cleanAllFromCache(){
		$q="DELETE FROM `kiror_profile_search_cache`;";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}

	public static function cleanAllFromLimit(){
		$q="DELETE FROM `kiror_profile_search_limiting`;";
		$dbc=XenForo_Application::get('db');
		$dbc->query($q);
		return;
	}

	public static function saveToCache($q,$r){
		$acc=time();
		$q=ProfileNdx_search_engine::mysql_escape_mimic_fromPhpDoc($q);
		$r=ProfileNdx_search_engine::mysql_escape_mimic_fromPhpDoc(serialize($r));
		$dbc=XenForo_Application::get('db');
		$qry="DELETE FROM `kiror_profile_search_cache` WHERE q='".$q."';";
		$dbc->query($qry);
		$qry="INSERT INTO `kiror_profile_search_cache` (q,d,r) VALUES
										('".$q."', ".$acc.", '".$r."');";
		$dbc->query($qry);
	}
	
	public static function computeSearchRequest($q){
		$visitor = XenForo_Visitor::getInstance();
		$uid=$visitor['user_id'];
		unset($visitor);
		$acc=time();
		$q=ProfileNdx_search_engine::mysql_escape_mimic_fromPhpDoc($q);
		$dbc=XenForo_Application::get('db');
		$qry="INSERT INTO `kiror_profile_search_limiting` (q,d,u) VALUES
									('".$q."', ".$acc.", ".$uid.");";
		$dbc->query($qry);
	}
	
	public static function getFromCache($q){
		$q=ProfileNdx_search_engine::mysql_escape_mimic_fromPhpDoc($q);
		$qry="SELECT * FROM `kiror_profile_search_cache` WHERE q='".$q."';";
		$dbc=XenForo_Application::get('db');
		$r=$dbc->fetchRow($qry)['r'];
		return unserialize($r);
	}

	public static function userCanSearch($u,$q){
		$q=ProfileNdx_search_engine::mysql_escape_mimic_fromPhpDoc($q);
		$dbc=XenForo_Application::get('db');
		$qry="SELECT COUNT(u) AS times, MAX(d) AS lastrq FROM `kiror_profile_search_limiting` WHERE u=".$u." GROUP BY u;";
		$r=$dbc->fetchRow($qry);
		$qry="SELECT d FROM `kiror_profile_search_cache` WHERE q='".$q."' LIMIT 1;";
		$available=($dbc->fetchRow($qry)['d'] != null);
		$acc=time();
		$xfopt=XenForo_Application::get('options');
		$searchblocktime=$xfopt->searchblocktime;
		$waittime=$searchblocktime-($acc-$r['lastrq']);
		if($available || $waittime<=0){
			ProfileNdx_search_engine::computeSearchRequest($q);
			return array('status'=>true,'wait'=>0);
		}
		return array('status'=>false,'wait'=>$waittime);
	}

	public function search($q)
	{
		if (trim($q)=='') return array();
		$rawProfiles = ProfileNdx_indexer_shared::recoverFromDbNdx('usersProfiles');
		$rawProfiles = $this->filterSearchInput($rawProfiles);
		$xfopt = XenForo_Application::get('options');
		$xfopt = $xfopt->searchable;
		$xfopt = explode(',', $xfopt);
		$xfo=array();
		foreach($xfopt as $k=>$v){
			$xfo[$k]=trim($v);
		}
		$res=array();
		$cfDic=ProfileNdx_indexer_shared::getCustomUserFieldsArray();
		foreach($rawProfiles as $userN=>$info){
			$match=array();
			foreach($info as $id=>$label){
				if(in_array($id,$xfo)){
					$n=0;
					$thisLab=$cfDic[$id];
					if(!is_array($label)){
						$label=array($label);
					}
					$searchable=array();
					foreach($label as $cufChng){
						$d='';
						if(array_key_exists($id.'_choice_'.$cufChng,$cfDic)){
							$d=$cfDic[$id.'_choice_'.$cufChng];
						}else{$d=$cufChng;};
						$searchable[]=$d;
					}
					$n=$this->repeatingTimes($this->plainifyArrayOfStrings($searchable),$q);
					if($n==0) continue;
					$match[$thisLab]=$n;
				}
			}
			$match['Total']=array_sum($match);
			if($match['Total']>0) {
				arsort($match);
				$res[]=array($match,array($userN,$rawProfiles[$userN]['username']));
			}
		}
		arsort($res);
		return $res;
	}

	private function htmlEntry($arr)
	{
		$resultFactory='
<li class="searchResult post primaryContent" data-author="<!--USERNUM-->">
	<div class="listBlock posterAvatar"><a href="<!--PROFILELINK-->" class="avatar" data-avatarhtml="true"><img src="<!--AVATAR-->" alt="<!--USERNAME-->" height="48" width="48"></a></div>
	<div class="listBlock main">
		<div class="titleText">
			<span class="contentType">Profile information</span>
			<h3 class="title"><a href="<!--PROFILEINFOLINK-->"><!--USERNAME--></a> <!--GENTXT--></h3>
		</div>
		<blockquote class="snippet">
			<a href="<!--PROFILEINFOLINK-->">
				<!--OCURRENCES-->
			</a>
		</blockquote>
	</div>
</li>';
		$prfLnk='index.php?members/'.$arr[1][0].'/';
		$prfNfoLnk=$prfLnk.'#info';
		$userModel = XenForo_Model::create('XenForo_Model_User');
		$user = $userModel->getUserById($arr[1][0]);
		$profImg=XenForo_Template_Helper_Core::callHelper('avatar',array($user,'s'));
				
		$total=$arr[0]['Total'];
		$gentxt='mentioned it '.$total.' times in the searched fields of the profile, where:';
		$resultFactory=str_replace('<!--GENTXT-->'         ,$gentxt,$resultFactory);
		$resultFactory=str_replace('<!--USERNUM-->'        ,$arr[1][0],$resultFactory);
		$resultFactory=str_replace('<!--PROFILEINFOLINK-->',$prfNfoLnk,$resultFactory);
		$resultFactory=str_replace('<!--AVATAR-->'         ,$profImg,$resultFactory);
		$resultFactory=str_replace('<!--USERNAME-->'       ,$arr[1][1],$resultFactory);
		$resultFactory=str_replace('<!--PROFILELINK-->'    ,$prfLnk,$resultFactory);
		unset($arr[0]['Total']);
		$octbl='<table>';
		foreach($arr[0] as $k=>$v){
			$octbl.='<tr><td>'.$k.': <b>'.$v.'</b> times'.'</td></tr>';
		}
		$octbl.='</table>';
		$resultFactory=str_replace('<!--OCURRENCES-->',$octbl,$resultFactory);
		return $resultFactory;
	}

	public function toHtml($r)
	{
		$s='';
		foreach($r as $entry) $s.=$this->htmlEntry($entry);
		return $s;
	}
}
