<?php

	class Extension_Documenter extends Extension {

		public function about() {
			return array(
				'name'			=> 'Documenter',
				'version'		=> '0.9.3',
				'release-date'	=> '2009-01-16',
				'author'		=> array(
					'name'			=> 'craig zheng',
					'email'			=> 'craig@symphony-cms.com'
				),
				'description'	=> 'Document your back end for clients or users.'
			);
		}

		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 'System',
					'name'		=> 'Documentation',
					'link'		=> '/'
				)
			);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => '__SavePreferences'
				),
				array(
					'page' 		=> '/backend/',
					'delegate' 	=> 'InitaliseAdminPageHead',
					'callback' 	=> 'loadAssets'
				),
				array(
					'page' 		=> '/backend/',
					'delegate'	=> 'AppendElementBelowView',
					'callback'	=> 'appendDocs'
				)
			);
		}

		public function loadAssets($context) {
			$page = $context['parent']->Page;
			$assets_path = '/extensions/documenter/assets/';

			$page->addStylesheetToHead(URL . $assets_path . 'documenter.css', 'screen', 120);
			$page->addScriptToHead(URL . $assets_path . 'documenter.js', 110);
		}

		public function appendDocs($context) {
			$current_page = str_replace(URL . '/symphony', '', $context['parent']->Page->_Parent->getCurrentPageURL());

			if (preg_match('/edit/',$current_page)){
				$pos = strripos($current_page, '/edit/');
				$current_page = substr($current_page,0,$pos + 6);
			}
			$pages = $this->_Parent->Database->fetch("
				SELECT
					d.pages, d.id
				FROM
					`tbl_documentation` AS d
				ORDER BY
					d.pages ASC
			");

			foreach($pages as $key => $value) {
				if(strstr($value['pages'],',')){
					$list = explode(',',$value['pages']);
					foreach($list as $item){
						$pages[] = array('id' => $value['id'], 'page' => $item);
					}
					unset($pages[$key]);
				}
			}

		    ###
            # Delegate: appendDocsPre
			# Description: Allow other extensions to add their own documentation page
			Administration::instance()->ExtensionManager->notifyMembers('appendDocsPre',
                '/backend/', array(
                    'pages' => &$pages
                )
            );

            $doc_items = array();

			foreach($pages as $page){
				if(in_array($current_page,$page)) {
    				if(isset($page['id'])) {
    					$doc_items[] = $this->_Parent->Database->fetchRow(0, "
    						SELECT
    							d.title, d.content_formatted
    						FROM
    							`tbl_documentation` AS d
  							WHERE
                                 d.id REGEXP '{$page['id']}'
                            LIMIT 1
                         ");

                    } else {
                        ###
                        # Delegate: appendDocsPost
                        # Description: Allows other extensions to insert documentation for the $current_page
                        Administration::instance()->ExtensionManager->notifyMembers('appendDocsPost',
                            '/backend/', array(
                                'doc_item' => &$doc_items
                            )
                        );
                    }
				}
			}

            /* Allows a page to have more then one documentation source */
            if(!empty($doc_items)) {
                $backend_page = &$context['parent']->Page->Form->getChildren();
                $navigation = $backend_page[1];

                $listitem = new XMLElement('li', NULL, array('id' => 'doc_item'));
                $link = Widget::Anchor($this->_Parent->Configuration->get('button-text', 'documentation'), '#', __('View Documentation'), NULL, 'doc_link');
                $listitem->appendChild($link);

                $docs = new XMLElement('div', NULL, array('id' => 'docs'));

                foreach($doc_items as $doc_item) {
                    if(isset($doc_item['title'])) {
                        $docs->appendChild(
                            new XMLElement('h2', $doc_item['title'])
                        );
                    }

                    $docs->appendChild(
                        new XMLElement('div', $doc_item['content_formatted'])
                    );

                    $listitem->appendChild($docs);
                }

                $navigation->appendChild($listitem);
            }
		}

		public function uninstall() {
			$this->_Parent->Database->query("DROP TABLE `tbl_documentation`;");
			Administration::instance()->Configuration->remove('text-formatter', 'documentation');
			Administration::instance()->Configuration->remove('button-text', 'documentation');
			Administration::instance()->saveConfig();
		}

		public function install() {
			$this->_Parent->Database->query(
				"CREATE TABLE `tbl_documentation` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`title` varchar(255),
					`pages` varchar(255),
					`content` text,
					`content_formatted` text,
					PRIMARY KEY (`id`)
				);");
			Administration::instance()->Configuration->set('text-formatter', 'pb_markdownextra', 'documentation');
			Administration::instance()->Configuration->set('button-text', 'Help', 'documentation');
			Administration::instance()->saveConfig();
			return;
		}

		public function __SavePreferences($context){

			if(!is_array($context['settings'])) $context['settings'] = array('documentation' => array('text-formatter' => 'none'));

			elseif(!isset($context['settings']['documentation'])){
				$context['settings']['documentation'] = array('text-formatter' => 'none');
			}

		}

		public function appendPreferences($context){

			include_once(TOOLKIT . '/class.textformattermanager.php');

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Documentation'));

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');

		// Input for button text
			$label = Widget::Label(__('Button Text'));
			$input = Widget::Input('settings[documentation][button-text]', $this->_Parent->Configuration->get('button-text', 'documentation'), 'text');

			$label->appendChild($input);
			$div->appendChild($label);

			$TFM = new TextformatterManager($this->_Parent);
			$formatters = $TFM->listAll();

		// Text formatter select
			$label = Widget::Label(__('Text Formatter'));

			$options = array();

			$options[] = array('none', false, __('None'));

			if(!empty($formatters) && is_array($formatters)){
				foreach($formatters as $handle => $about) {
					$options[] = array($handle, ($this->_Parent->Configuration->get('text-formatter', 'documentation') == $handle), $about['name']);
				}
			}

			$input = Widget::Select('settings[documentation][text-formatter]', $options);

			$label->appendChild($input);
			$div->appendChild($label);

			$group->appendChild($div);
			$context['wrapper']->appendChild($group);
		}
	}
