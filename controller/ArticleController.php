<?php
class ArticleController extends Controller
{
	public function listAction($query_params)
	{
		if (empty($query_params) || !is_array($query_params))
		{
			header("Location: /index/notfound");
			return;
		}

		$params = $this->getArticle(intval($query_params[0]));
		if (!TechlogTools::isMobile())
			$this->display(__METHOD__, $params);
		else
			$this->display(__CLASS__.'::mobileAction', $params);
	}

	public function testAction($query_params)
	{
		if (!$this->is_root)
		{
			header("Location: /index/notfound");
			return;
		}

		$draft_id = (isset($query_params[0]) && intval($query_params[0]) > 0)
			? intval($query_params[0]) : '';
		$contents = file_exists(DRAFT_PATH.'/draft'.$draft_id.'.tpl')
			? TechlogTools::pre_treat_article(
				file_get_contents(DRAFT_PATH.'/draft'.$draft_id.'.tpl')
			) : '';

		if (StringOpt::spider_string(
			$contents, '"page-header"', '</div>') === null)
		{
			$contents = '<div class="page-header"><h1>草稿'
				.$draft_id.'</h1></div>'.$contents;
		}

		$index = TechlogTools::get_index($contents);

		$params = array(
			'tags'	=> array(),
			'title'	=> '测试页面',
			'contents'	=> $contents,
			'inserttime'	=> '',
			'title_desc'	=> '仅供测试',
			'article_category_id'	=> 3,
		);
		if (count($index) > 0)
			$params['indexs'] = $index;

		if (!TechlogTools::isMobile())
			$this->display(__CLASS__.'::listAction', $params);
		else
			$this->display(__CLASS__.'::mobileAction', $params);
	}

	private function getArticle($article_id)
	{
		$params = array();

		$params = array('eq' => array('article_id' => $article_id));
		if (!$this->is_root)
			$params['lt'] = array('category_id' => 5);
		$article = Repository::findOneFromArticle($params);

		if ($article == false)
		{
			header("Location: /index/notfound");
			return;
		}

		$params['tags']		= SqlRepository::getTags($article_id);
		$params['title']	= $article->get_title();
		$params['indexs'] = json_decode($article->get_indexs());
		$params['contents'] = \
			TechlogTools::pre_treat_article($article->get_draft());
		$params['title_desc']	= $article->get_title_desc();
		$params['article_category_id']	= $article->get_category_id();

		if (
			StringOpt::spider_string(
				$params['contents'],
				'"page-header"',
				'</div>'
			) === null
			&& !TechlogTools::isMobile()
		)
		{
			$params['contents'] = '<div class="page-header"><h1>'
				.$article->get_title()
				.'</h1></div>'.$params['contents'];
		}

		$article->set_access_count($article->get_access_count() + 1);
		Repository::persist($article);

		$params['inserttime'] = $article->get_inserttime()
			.'&nbsp;&nbsp;&nbsp;最后更新: '
			.$article->get_updatetime()
			.'&nbsp;&nbsp;&nbsp;访问数量：'
			.($article->get_access_count()+1);

		return $params;
	}
}
?>
