<?php

class ProfileNdx_statistics_actions extends XenForo_ControllerPublic_Abstract
{
	public static function validateString($field, $value, $error)
	{
		return ProfileNdx_indexer_shared::validateString($field, $value, $error);
	}

	public function recoverFromDbNdx($id)
	{
		return ProfileNdx_indexer_shared::recoverFromDbNdx($id);
	}
	
	public function sendToDbNdx($id,$cont)
	{
		ProfileNdx_indexer_shared::createEmptyDbNdx();
		$dbc  = XenForo_Application::get('db');
		$cont = serialize($cont);
		$cont = ProfileNdx_indexer_shared::mysql_escape_mimic_fromPhpDoc($cont, $dbc);
		$dbc->query("INSERT INTO kiror_index_profile VALUES ('".$id."','".$cont."')
					 ON DUPLICATE KEY UPDATE things='".$cont."';");
	}

	public static function sendToDbNdx_static($id,$cont)
	{
		$dbc  = XenForo_Application::get('db');
		$cont = serialize($cont);
		$cont = ProfileNdx_indexer_shared::mysql_escape_mimic_fromPhpDoc($cont, $dbc);
		$dbc->query("INSERT INTO kiror_index_profile VALUES ('".$id."','".$cont."')
					 ON DUPLICATE KEY UPDATE things='".$cont."';");
	}
	
	public function processStat()
	{
		$lastUpdate       = $this->recoverFromDbNdx('lastchange');
		$cProfilesFields  = $this->recoverFromDbNdx('fieldLabels');
		$rawProfiles      = $this->recoverFromDbNdx('usersProfiles');
		$xfopt = XenForo_Application::get('options');
		$xfopt = $xfopt->toshow;
		$xfopt = explode(',', $xfopt);
		$xfo=array();
		foreach($xfopt as $k=>$v){
			$xfo[$k]=trim($v);
		}
		if (count($xfo)==1 && count($xfo[0])==1){
			$xfo=array();
			$i=0;
			foreach($cProfilesFields as $k=>$v){
				if (!preg_match('/_choice_/',$k)){
					$xfo[$i]=$k;
					$i++;
				}
			}
			unset($i);
		}
		unset($xfopt);
		unset($k);
		unset($v);
		$options=array();
		foreach($cProfilesFields as $k=>$v){
			if (!preg_match('/_choice_/',$k)){
				if (in_array($k,$xfo)){
					$options[$k]=$v;
				}
			}
		}
		if (in_array('gender',$xfo)){
			$choices=array('gender'=>array(''=>'Nothing was selected','male'=>'Male','female'=>'Female'));
		} else {$choices=array();}
		foreach($cProfilesFields as $k=>$v){
			if (preg_match('/_choice_/',$k)){
				$xploded = explode('_choice_',$k);
				$chcont  = $xploded[0];
				$chopt   = $xploded[1];
				if (in_array($chcont,$xfo)){
					if (!array_key_exists($chcont,$choices)){
						$choices[$chcont]['']='Nothing was selected';
					}
					else if (!array_key_exists('',$choices[$chcont])){
						$choices[$chcont]['']='Nothing was selected';
					}
					$choices[$chcont][$chopt]=$v;
				}
			}
		}
		unset($xploded);
		unset($chcont);
		unset($chopt);
		unset($k);
		unset($v);
		$profiles=array();
		foreach($rawProfiles as $uid=>$prof){
			$user=array();
			foreach($prof as $infoid=>$infocont){
				if(in_array($infoid,$xfo)){
					if (is_array($infocont)){
						if(count($infocont)==0){$infocont=array('');};
						$i=0;
						foreach($infocont as $choice){
							$user[$infoid][$i++]=$choices[$infoid][$choice];
							unset($choice);
						}
						unset($i);
					}
					else if(array_key_exists($infoid,$choices)){
						$user[$infoid]=$choices[$infoid][$infocont];
					}
					else{
						$user[$infoid]=''.$infocont;
					}
				}
				unset($infoid);
				unset($infocont);
			}
			$profiles[$uid]=$user;
			unset($prof);
			unset($user);
		}
		$counted=$this->doTheBoringCountingPart($xfo,$profiles,$options);
		$counted=$this->doTheBoringCountingPart($xfo,$profiles,$options);
		$processStat=array($lastUpdate,$options,$profiles,$counted);
		$this->sendToDbNdx('preprocessedStatistic',$processStat);
	}
	
	public function doTheBoringCountingPart($toDo,$profile,$labels){;
		$counting=array();
		foreach($labels as $id=>$field){
			$counting[$id]=array('label'=>$field,'disp'=>0,'count'=>0,'given'=>array());
		}
		foreach($profile as $user){
			foreach($user as $infok=>$infov){
				$counting[$infok]['disp']++;
				if (is_array($infov) || strlen(trim($infov))>0){
					$counting[$infok]['count']++;
				}
				if (is_string($infov)){$a=array(0=>$infov);};
				if (is_array($infov)){$a=$infov;};
				foreach($a as $b){
					if(array_key_exists($b,$counting[$infok]['given']))
					{
						$counting[$infok]['given'][$b]++;
					}
					else
					{
						$counting[$infok]['given'][$b]=1;
					}
				}
			}
		}
		return $counting;
	}
	
	public static function percentage($from,$total){
		if ($total==0 || $from>$total) return '??? ';
		$decimalCases=2;
		return number_format((100*$from)/$total,$decimalCases);
	}
	
	public function renderHtml($stat)
	{
		$html='
		<style>table {border-spacing: 15px 2px; border-collapse: separate;}</style>
		<li id="info" class="profileContent">
			<div class="section">
				<h3 class="textHeading">Overview</h3>
				<div class="primaryContent">
					<div class="pairsColumns aboutPairs">

<!--APPEND_OVERVIEW_HERE-->						

					</div>
				</div>
				<h3 class="textHeading">Detailed</h3>
				<div class="primaryContent">
					<div class="pairsColumns aboutPairs">

<!--APPEND_DATA_HERE-->						

					</div>
				</div>
			</div>
		</li>
';
        $nl='
';
		$over='';
		if(count($stat)<3) return '';
		foreach($stat[3] as $arr){
			if(!array_key_exists('count',$arr)) continue;
			if(!array_key_exists('disp',$arr)) continue;
			if($arr['disp']==0) $pct=0;
			else $pct=$this->percentage($arr['count'],$arr['disp']);
			$over.=('<dl><dt>'.$arr['label'].': '.'</dt><dd>'.$arr['count'].
			' of '.$arr['disp'].' countable users answered to this, which represents '.
			$pct.'% of the sample.</dd></dl><br />'.$nl);
			unset($pct);
		}
		$fulldata='';
		foreach($stat[3] as $arr){
			if ($arr['count']==0) continue;
			if (count($arr['disp'])==0) continue;
			$fulldata.=('<dl><dt>'.$arr['label'].': '.'</dt><dd>'.$arr['count'].
			' of '.$arr['disp'].' countable users answered to this, which represents '.
			$this->percentage($arr['count'],$arr['disp']).'% of the sample.'.$nl);
			$fulldata.='<br/><br/><h4>Distribution:</h4>';
			$fulldata.='<table>'.$nl;
			arsort($arr['given']);
			foreach($arr['given'] as $msg=>$times){
				$fulldata.='<tr><td>'.$msg.'</td><td>'.$times.'</td><td>'.$this->percentage($times,$arr['count']).'%</td></tr>'.$nl;
			}
			$fulldata.='</table>';
			$fulldata.='<br/><br/><br/>';
			$fulldata.='</dd></dl>'.$nl;
		}
		$html=str_replace('<!--APPEND_OVERVIEW_HERE-->',$over,$html);
		$html=str_replace('<!--APPEND_DATA_HERE-->',$fulldata,$html);
		return $html;
	}
	
	public function actionIndex()
    {
		$preprocessedStat = $this->recoverFromDbNdx('preprocessedStatistic');
		if (sizeof($preprocessedStat)==0) {
			$this->processStat();
			return $this->actionIndex();
		}
		$rawhtml=$this->renderHtml($preprocessedStat);
		$xfopt = XenForo_Application::get('options');
		$xfopt = $xfopt->inviteToJoin;
		$viewParams = array(
						'join'=>$xfopt,
						'stats'=>$rawhtml,
						'date'=>$preprocessedStat[0]
						);
        return $this->responseView(
            'XenForo_ViewPublic_Base',
            'kiror_statistics_index',
            $viewParams
        );
    }
}
