<?php 

class AddToCampaign_Toolbar extends RequestHandler {

	private static $allowed_actions = array(
		'addToCampaign'
	);

	protected $controller, $name;

	public function __construct($controller, $name) {
		parent::__construct();

		$this->controller = $controller;
		$this->name = $name;
	}

	public function forTemplate() {
		return sprintf(
			'<div id="ss-addtocampaign-url" data-url="%s"></div>',
			Controller::join_links($this->controller->Link(), $this->name, 'addToCampaign', 'forTemplate')
		);
	}

	public function addToCampaign() {
		$form = new Form(
			$this->controller,
			"{$this->name}/addToCampaignForm",
			new FieldList(
				$headerWrap = new CompositeField(
					new LiteralField(
						'Heading',
						sprintf('<h3>%s</h3>',
							_t('Campaigns.AddToCampaign', 'Add To Campaign'))
					)
				),

				$contentComposite = new CompositeField(
					$campaignDropdown = DropdownField::create(
						'Campaign',
						'',
						array(
							'1' => 'Campaign 1',
							'2' => 'Campaign 2',
							'3'=> 'Campaign 3'
						)
					)
				)
			),
			new FieldList(
				FormAction::create('addToCampaign', 'Add to campaign')
					->addExtraClass('ss-ui-action-constructive add-to-campaign')
			)
		);

		$headerWrap->addExtraClass('cms-content-header');
		$contentComposite->addExtraClass('ss-addtocampaign-content');
		$campaignDropdown->addExtraClass('noborder');

		$form->unsetValidator();
		$form->loadDataFrom($this);
		$form->addExtraClass('ss-addtocampaign-form');

		$this->extend('addToCampaignForm', $form);

		return $form;
	}
}
