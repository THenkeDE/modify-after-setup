<?php

class IndexController extends pm_Controller_Action
{

	private $Meta = null;
	private $basefoldername = null;
	private $seperatefolder = null;

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
		$this->basefoldername = pm_Settings::get('basefoldername');
		$this->seperatefolder = pm_Settings::get('seperatefolder') == '1' ? true : false;
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

	public function enableAction()
	{
		if( $this->getRequest()->isGet() ){
			if( ($domainId = $this->getRequest()->getParam('domain_id')) > 0 ){
				foreach( (pm_Domain::getAllDomains()) as $domainData ){
					if( $domainData->getId() == $domainId ){
						$sessionFolder = $domainData->getHomePath() . '/' . $this->basefoldername . ( $this->seperatefolder === TRUE ? '_' . $domainData->getName() : '');
						$fm = new pm_FileManager( $domainData->getId() );
						if( !is_dir($sessionFolder) ){
							$fm->mkdir($sessionFolder, '1750' );
						}
						$tmpfile = sys_get_temp_dir() . '/' . $domainData->getName() . '.ini';
						$tmpcontent = 'session.save_path=' . $sessionFolder . '/' . "\n";
						file_put_contents($tmpfile,$tmpcontent);
						$res = pm_ApiCli::call('site', ['--update-php-settings',$domainData->getName(),'-settings',$tmpfile]);
						unlink($tmpfile);
						$this->_status->addMessage('info', $this->lmsg('sessionfolder_enabled',[
							'folder' => $sessionFolder,
							'domain' => $domainData->getName()
						]));
						unset($_SESSION[$this->Meta->id]);
					}
				}
			}
		}
		$this->_redirect( pm_Context::getActionUrl('index/usage'), ['exit' => true, 'prependBase' => false ]);
		return;
	}


	public function disableAction()
	{
		if( $this->getRequest()->isGet() ){
			if( ($domainId = $this->getRequest()->getParam('domain_id')) > 0 ){
				foreach( (pm_Domain::getAllDomains()) as $domainData ){
					if( $domainData->getId() == $domainId ){
						$tmpfile = sys_get_temp_dir() . '/' . $domainData->getName() . '.ini';
						$tmpcontent = "session.save_path=;\n";
						file_put_contents($tmpfile,$tmpcontent);
						$res = pm_ApiCli::call('site', ['--update-php-settings',$domainData->getName(),'-settings',$tmpfile]);
						unlink($tmpfile);
						$this->_status->addMessage('info', $this->lmsg('sessionfolder_disabled',[
							'folder' => $sessionFolder,
							'domain' => $domainData->getName()
						]));
						unset($_SESSION[$this->Meta->id]);
					}
				}
			}
		}
		$this->_redirect( pm_Context::getActionUrl('index/usage'), ['exit' => true, 'prependBase' => false ]);
		return;
	}

	private function _getUsageList()
	{
		$usage = ['folder' => [], 'domains' => [], 'default' => [], 'ids' => [] ];
		$_configs = $this->_getPhpConfigs(); // query XML API for PHP configurations
		foreach( (pm_Domain::getAllDomains()) as $domainData ){
			$usage['ids'][$domainData->getName()] = $domainData->getId();
			if( isset($_configs[$domainData->getId()]) ){
				if( !isset($usage['folder'][$_configs[$domainData->getId()]]) ){
					$usage['folder'][$_configs[$domainData->getId()]] = [];
				}
				$usage['folder'][$_configs[$domainData->getId()]][] = $domainData->getName();
				$usage['domains'][$domainData->getName()] = $_configs[$domainData->getId()];
			} else {
				$usage['domains'][$domainData->getName()] = false;
				$usage['default'][$domainData->getName()] = true;
			}
		}
		$options = [
			'defaultSortField' => 'domain',
			'defaultSortDirection' => pm_View_List_Simple::SORT_DIR_DOWN,
		];
		$data = [];
		foreach( $usage['domains'] as $domain => $sessionFolder ){
			$data[] = [
				'domain'	=> $domain,
				'sessionfolder'	=> $sessionFolder != FALSE ? $sessionFolder : $this->lmsg('system_default_label'),
				'own'		=> isset($usage['default'][$domain]) ? $this->_actionButton('on', pm_Context::getActionUrl('index','enable') . '?domain_id='.$usage['ids'][$domain]) : $this->_actionButton('off', pm_Context::getActionUrl('index','disable') . '?domain_id='.$usage['ids'][$domain] )
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
			'own' => [
				'title' => $this->lmsg('own_folder_label'),
				'noEscape' => true,
				'sortable' => true,
				'searchable' => false,
			],
		]);
		$list->setDataUrl(['action' => 'list-usage']);
		return $list;
	}

	private function _actionButton( $mode = 'on', $url )
	{
		return $this->lmsg('button_template',[
			'mode'	=> $mode == 'off' ? 'restart' : 'add',
			'url'	=> $url,
			'msg'	=> $this->lmsg('button_'.($mode == 'off' ? 'activate' : 'deactivate').'_tooltip'),
			'text'	=> $this->lmsg('button_'.($mode == 'off' ? 'activate' : 'deactivate').'_label')
		]);
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

	private function _getPhpConfigs()
	{
		$_configs = [];
		$request = '<site><get><filter/><dataset><hosting/></dataset></get></site>';
		foreach( pm_ApiRpc::getService()->call($request)->site->get->result as $row ){
			if( isset($row->data->hosting->vrt_hst) ){
				foreach( $row->data->hosting->vrt_hst->property as $property ){
					if( $property->name == 'session.save_path' ){
						$_configs[(double)$row->id] = (string)$property->value;
					}
				}
			}
		}
		return $_configs;
	}

	public function loadMetaXml() : object
	{
		$meta = (new pm_ServerFileManager)->fileGetContents(pm_Context::getPlibDir() . 'meta.xml');
		$xml = simplexml_load_string($meta);
		return (object) json_decode(json_encode($xml));
	}

}
