<?php

namespace Converter;

use Generator\ActionList;
use Generator\Helper;
use Generator\Profile;
use LuaWriter\Element;

class Converter
{
	protected $fileName;
	protected $handle;
	/** @var Profile */
	protected $profile;
	protected $class;
	protected $spec;

	/**
	 * Converter constructor.
	 * @param $fileName
	 * @param Profile $profile
	 * @throws \Exception
	 */
	public function __construct($fileName, Profile $profile)
	{
		$this->fileName = $fileName;
		$this->handle = fopen($fileName, 'w');

		if (!$this->handle) {
			throw new \Exception('Could not open file: ' . $fileName);
		}

		$this->profile = $profile;
	}

	public function __destruct()
	{
		if (get_resource_type($this->handle) == 'stream') {
			fclose($this->handle);
		}
	}

	/**
	 * @throws \Exception
	 */
	public function convert()
	{
		$spellPrefix = $this->profile->spellPrefix;

		$spellList = new Element($this->handle, 0);
		$spellList->makeArray($spellPrefix, $this->profile->spellList->toArray());
		$spellList->write();

		$this->class = Helper::properCase($this->profile->class);
		$this->spec = Helper::properCase($this->profile->spec);

		$mainList = new Element($this->handle, 0);
		$this->writeActionList($this->profile->mainActionList, $mainList);
		$mainList->write();

		foreach ($this->profile->actionLists as $actionList) {
			$list = new Element($this->handle, 0);
			$this->writeActionList($actionList, $list);
			if (is_null($list)) {
				var_dump($actionList);
			}
			$list->write();
			$list->makeChildren()->makeNewline()->write();
		}

		//fclose($this->handle);
	}

	/**
	 * @param ActionList $list
	 * @param Element $element
	 * @throws \Exception
	 */
	protected function writeActionList(ActionList $list, Element $element)
	{
		$funcName = $list->getFunctionName();

		$children = [];

		$this->writeResources($list, $element, $children);

		foreach ($list->actions as $action) {
			$child = null;
//			echo $action->type . PHP_EOL;
			switch ($action->type) {
				case $action::TYPE_VARIABLE: //@TODO
					$children[] = $element->makeChildren()->makeComment($action->rawLine);
					$child = $element->makeChildren();
					$child->makeVariable($action->variableName, $action->variableValue);
					break;
				case $action::TYPE_SPELL:
					$children[] = $element->makeChildren()->makeNewline();
					$children[] = $element->makeChildren()->makeComment($action->rawLine);

					if ($action->spellCondition === true) {
						// unconditional spells
						$child = $element->makeChildren()->makeResult($action->spellName);
					} elseif ($action->spellCondition) {
						$child = $element->makeChildren();

						$result = $child->makeChildren();
						$result->makeResult($action->spellName);

						$child->makeCondition($action->spellCondition, [$result]);
					} else {
						$child = $element->makeChildren();
						$child->makeResult($action->spellName);
					}


					break;

				case $action::TYPE_CALL: //@TODO
				case $action::TYPE_RUN:
					$children[] = $element->makeChildren()->makeNewline();
					$children[] = $element->makeChildren()->makeComment($action->rawLine);

					if ($action->aplCondition) {
						$child = $element->makeChildren();

						$aplChild = $child->makeChildren();
						if ($action->type == $action::TYPE_CALL) {
							$aplChild->makeStatement($this->getAplListName($action->aplToRun));
						} else {
							$aplChild->makeResult($this->getAplListName($action->aplToRun));
						}


						$child->makeCondition($action->aplCondition, [$aplChild]);
					} else {
						$child = $element->makeChildren();

						if ($action->type == $action::TYPE_CALL) {
							$child->makeStatement($this->getAplListName($action->aplToRun));
						} else {
							$child->makeResult($this->getAplListName($action->aplToRun));
						}
					}

					$children[] = $element->makeChildren()->makeNewline();
					break;
				default:
					throw new \Exception('Unrecognized action type: ' . $action->type);
					break;
			}

			$children[] = $child;
		}

		$element->makeFunction($funcName, [], $children);
	}

	protected function writeResources(ActionList $list, Element $element, &$children)
	{
		$children[] = $element->makeChildren()->makeVariable('fd', 'MaxDps.FrameData');

		foreach ($list->resourceUsage as $key => $value) {
			if (!$value || $key == 'resources') {
				continue;
			}

			$children[] = $element->makeChildren()->makeVariable($key, 'fd.' . $key);
		}

		foreach ($list->resourceUsage->resources as $resource => $isUsed) {
			$children[] = $element->makeChildren()->makeVariable($resource, "UnitPower('player', Enum.PowerType.{$resource})");
		}
	}

	protected function getAplListName($apl)
	{
		$actionList = $this->profile->getActionListByName($apl);
		return $actionList->getFunctionName() . '()';
	}
}