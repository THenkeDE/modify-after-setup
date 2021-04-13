<?php

class IndexController extends pm_Controller_Action
{

	private $Meta = null;

	public function init()
	{
		parent::init();
		$this->Meta = $this->loadMetaXml();
		$this->view->pageTitle = $this->lmsg('pagetitle',[
			'extension' => $this->Meta->name,
			'version' => '<small>(v' . $this->Meta->version.'-'.$this->Meta->release.'</small>)',
			'icon' => pm_Context::getBaseUrl() . 'images/new-php-logo.svg' 
		]);
		$tabs = [
			[
				'title' => $this->lmsg('tab_title_index'),
				'action' => 'index'
			],
			[
				'title' => $this->lmsg('tab_title_config'),
				'action' => 'config'
			],
			[
				'title' => $this->lmsg('tab_title_usage'),
				'action' => 'usage'
			]
		];
		$this->view->tabs = $tabs;

	}

	public function indexAction()
	{
		$this->view->splash = $this->lmsg('index_teaser',[
			'icon' => pm_Context::getBaseUrl() . 'images/new-php-logo.svg' 
		]);
	}

	public function usageAction()
	{
		$list = $this->_getUsageList();
		$this->view->list = $list;
		$this->view->teaser = $this->lmsg('usage_teaser');
	}

	public function listUsageAction()
	{
		$list = $this->_getUsageList();
		$this->_helper->json($list->fetchData());
	}

	private function _getUsageList()
	{
		$usage = ['folder' => [], 'domains' => [] ];

		if( !isset($_SESSION[$this->Meta->id]) ){
			$_SESSION[$this->Meta->id] = ['usage_ts' => 0, 'usage_data' => [] ];
		}

		if( $_SESSION[$this->Meta->id]['usage_ts'] + 60 < time() ){
			foreach( (pm_Domain::getAllDomains()) as $domainData ){
				$res = pm_ApiCli::call('site', ['--show-php-settings',$domainData->getName()]);
				if( preg_match('/session\.save_path\s+\=\s+('.preg_quote($domainData->getHomePath(),'/').'(.+?))(;|\n|$)/si',$res['stdout'],$m ) ){
					if( !isset($usage['folder'][$m[1]]) ){
						$usage['folder'][$m[1]] = [];
					}
					$usage['folder'][$m[1]][] = $domainData->getName();
					$usage['domains'][$domainData->getName()] = $m[1];
				}
			}
			$_SESSION[$this->Meta->id]['usage_ts'] = time();
			$_SESSION[$this->Meta->id]['usage_data'] = $usage;
		} else {
			$usage = $_SESSION[$this->Meta->id]['usage_data'];
		}

		$options = [
			'defaultSortField' => 'domain',
			'defaultSortDirection' => pm_View_List_Simple::SORT_DIR_DOWN,
		];

		$data = [];
		foreach( $usage['domains'] as $domain => $sessionFolder ){
			$data[] = [
				'domain' => $domain,
				'sessionfolder' => $sessionFolder,
				'checktime' => date($this->lmsg('checktime_fmt'),$_SESSION[$this->Meta->id]['usage_ts'])
			];
		}

		$list = new pm_View_List_Simple($this->view, $this->_request, $options);
		$list->setData($data);
		$list->setColumns([
			'domain' => [
				'title' => $this->lmsg('domain_label'),
				'noEscape' => true,
				'searchable' => true,
				'sortable' => true,
			],
			'sessionfolder' => [
				'title' => $this->lmsg('folder_label'),
				'noEscape' => true,
				'sortable' => true,
				'searchable' => true,
			],
			'checktime' => [
				'title' => $this->lmsg('checktime_label'),
				'noEscape' => true,
				'sortable' => true,
				'searchable' => false,
			],
		]);

		$list->setDataUrl(['action' => 'list-usage']);
		return $list;
	}

	public function configAction()
	{
		$form = new pm_Form_Simple();
		$form->addElement('text', 'basefoldername', [
			'label'		=> $this->lmsg('form_basefoldername_label'),
			'placeholder'	=> $this->lmsg('form_basefoldername_placeholder'),
			'escape'	=> false,
			'value'		=> pm_Settings::get('basefoldername'),
		]);
		$form->addElement('select', 'seperatefolder', [
			'label'		=> $this->lmsg('form_seperatefolder_label'),
			'multiOptions'	=> ['1' => 'yes', '0' => 'no'],
			'value'		=> pm_Settings::get('seperatefolder'),
			'required'	=> true,
		]);

		$form->addControlButtons([
			'sendTitle'	=> $this->lmsg('form_save_config'),
			'cancelHidden'	=> true,
			'hideLegend'	=> true,
			'withSeparator'	=> false
		]);

		if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
			pm_Settings::set('basefoldername', $form->getValue('basefoldername'));
			pm_Settings::set('seperatefolder', $form->getValue('seperatefolder') );
			$this->_status->addMessage('info', $this->lmsg('form_save_success'));
			$this->_helper->json(['redirect' => pm_Context::getActionUrl('index/config')]); 
		}

		$this->view->form = $form;
	}

	public function loadMetaXml() : object
	{
		$meta = (new pm_ServerFileManager)->fileGetContents(pm_Context::getPlibDir() . 'meta.xml');
		$xml = simplexml_load_string($meta);
		return (object) json_decode(json_encode($xml));
	}

}
