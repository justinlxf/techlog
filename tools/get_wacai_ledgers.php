<?php
require_once (__DIR__.'/../app/register.php');

$options = getopt('m:t:');
if (!isset ($options['m']) || !isset($options['t']))
{
	echo 'usage: php get_wacai_datas.php -m month -t token(key: wctk)'
		.PHP_EOL;
	exit;
}

HttpCurl::set_cookie('wctk='.trim($options['t']));
$url = 'https://www.wacai.com/biz/ledger_list.action?'
	.'cond.date='.$options['m'].'-01&cond.date_end='.$options['m'].'-31'
	.'&cond.reimbursePrefer=0&cond.withDaySum=false&pageInfo.pageIndex=';
$pageCount = 1;
for ($i=1; $i<=$pageCount; $i++)
{
	$ret = HttpCurl::get($url.$i);
	if ($ret['body'] == false)
	{
		echo 'get_earnings ERROR: url '.$url.$i.'; error '.$ret['error'].PHP_EOL;
		exit;
	}
	$infos = json_decode($ret['body'], true);
	if ($infos == false)
	{
		echo 'get_earnings ERROR: url '.$url.$i.'; body '.$ret['body'].PHP_EOL;
		exit;
	}
	if ($i == 1)
		$pageCount = $infos['pi']['pageCount'];
	$ledgers = $infos['ledgers'];
	foreach ($ledgers as $infos)
	{
		if (empty($infos['id']) || !isset($infos['date']) || !isset($infos['recType']))
		{
			echo 'INFOS ERROR : '.json_encode($infos).PHP_EOL;
			continue;
		}
		$model = new LedgersModel(
			array(
				'esid' => isset($infos['id']) ? $infos['id'] : '',
				'date' => isset($infos['date']) ? $infos['date'] : '',
				'recType' => isset($infos['recType']) ? $infos['recType'] : '',
				'tag' => isset($infos['tag']) ? $infos['tag'] : '',
				'comment' => isset($infos['comment']) ? $infos['comment'] : '',
				'inserttime' => 'now()'
			)
		);
		if ($model->get_recType() == 3)
		{
			$model->set_type(-1);
			$model->set_fromAcc($infos['srcAcc']);
			$model->set_toAcc($infos['tgtAcc']);
			$model->set_money($infos['transin']);
			if (!empty($infos['srcMflag'])) {
				$infos['mflag'] = $infos['srcMflag'];
			} else {
				$infos['mflag'] = '￥';
			}
		}
		else if ($model->get_recType() == 4)
		{
			$model->set_type($infos['type']);
			$model->set_fromAcc($infos['acc']);
			$model->set_toAcc($infos['srcAcc']);
			$model->set_money($infos['money']);
		}
		else if ($model->get_recType() == 5)
		{
			$model->set_type($infos['type']);
			$model->set_fromAcc($infos['srcAcc']);
			$model->set_toAcc($infos['acc']);
			$model->set_money($infos['money']);
		}
		else
		{
			$model->set_type($infos['type']);
			$model->set_fromAcc($infos['acc']);
			$model->set_toAcc('');
			$model->set_money($infos['money']);
		}
		switch ($infos['mflag'])
		{
		case '￥':
			$model->set_currency('人民币');
			break;
		case '◎':
			$model->set_currency('虚拟币');
			break;
		case '$':
			$model->set_currency('美元');
			break;
		default:
			$model->set_currency('');
			break;
		}
		$category = explode('-', $infos['typeTitle']);
		$model->set_category($category[0]);
		$model->set_subcategory((isset($category[1]) ? $category[1] : ''));
		$id = Repository::persist($model);
		if (!is_numeric($id) || $id == 0) {
			echo $id."\t".json_encode($infos).PHP_EOL;
			continue;
		} else {
			echo 'INFO: BACKUP'."\t".$infos['id']."\t"
				.json_encode($infos).PHP_EOL;
		}

		$account = Repository::findOneFromAccount(
			array('eq' => array('esid' => $model->get_fromAcc()))
		);
		if ($account != false)
		{
			if ($model->get_recType() == 2)
				$account->set_money($model->get_money() + $account->get_money());
			else
				$account->set_money($account->get_money() - $model->get_money());
			$account->set_orderNo($account->get_orderNo() + 1);
			$account->set_updatetime('now()');
			$id = Repository::persist($account);
		}

		if (empty($model->get_toAcc()))
			continue;
		$account = Repository::findOneFromAccount(
			array('eq' => array('esid' => $model->get_toAcc()))
		);
		if ($account == false)
			continue;
		$account->set_money($model->get_money() + $account->get_money());
		$account->set_orderNo($account->get_orderNo() + 1);
		$account->set_updatetime('now()');
		$id = Repository::persist($account);
	}
	sleep(3);
}
?>
