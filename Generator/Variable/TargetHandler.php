<?php

namespace Generator\Variable;

use Generator\Helper;

class TargetHandler extends Handler
{
	public $handledPrefixes = ['target'];

	public function handle($lexer, $variableParts, &$output)
	{
		switch ($variableParts[1]) {
			case 'health':
				$output[] = 'targetHp'; break;
			case 'time_to_die':
				$this->action->actionList->resourceUsage->timeToDie = true;
				$output[] = 'timeToDie'; break;
			case '':
				$output[] = $variableParts[0]; break;
			case 'debuff':
				$output[] = implode('.', $variableParts); break;
			default:
				throw new \Exception(
					'Unrecognized target variable: ' . $variableParts[1] . ' expression: ' . implode('.', $variableParts)
				);
		}
	}
}