<?php
/*
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Test\Service;

use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use MetaModel;


/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class AnonymizerServiceTest extends ItopDataTestCase
{
	const USE_TRANSACTION = true;
	const CREATE_TEST_ORG = false;
	private $TEST_LOG_FILE;

	protected function setUp(): void
	{
		parent::setUp();
		require_once 'AnonymizationTestActionFactory.php';
		require_once 'AnonymizationTestAction.php';
		$this->TEST_LOG_FILE = APPROOT.'log/test.log';
		AnonymizerLog::Enable($this->TEST_LOG_FILE);
		@unlink($this->TEST_LOG_FILE);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		if (file_exists($this->TEST_LOG_FILE)) {
			$sLogs = file_get_contents($this->TEST_LOG_FILE);
			$this->debug($sLogs);
		}
	}

	/**
	 * @dataProvider GetNextActionProvider
	 * @param $sExpectedAction
	 * @param $sCurrentAction
	 * @param $aActions
	 *
	 * @return void
	 * @throws \ReflectionException
	 */
	public function testGetNextAction($sExpectedAction, $sCurrentAction, $aActions)
	{
		$oService = new AnonymizerService(0);
		$oService->SetActions($aActions);
		$this->assertEquals($sExpectedAction, $this->InvokeNonPublicMethod(AnonymizerService::class, 'GetNextAction', $oService, [$sCurrentAction]));
	}

	public function GetNextActionProvider()
	{
		return [
			'empty' => [null, null, []],
			'first' => ['action1', null, ['action1', 'action2']],
			'2nd' => ['action2', 'action1', ['action1', 'action2']],
			'last' => [null, 'action2', ['action1', 'action2']],
		];
	}

	/**
	 * @dataProvider ProcessAnonymizationTaskProvider
	 * @param $sExpectedStatus
	 * @param $sInitialStatus
	 * @param $sInitialAction
	 * @param $sExpectedActionParams
	 * @param $aActions
	 * @param $aActionParams
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \ReflectionException
	 */
	public function testProcessAnonymizationTask($sExpectedStatus, $sInitialStatus, $sInitialAction, $sExpectedActionParams, $aActions, $aActionParams)
	{
		$oService = new AnonymizerService(10);
		// Parameters injection
		$oService->SetActions($aActions);
		$oService->SetAnonymizationActionFactory(new AnonymizationTestActionFactory($aActionParams));

		$oTask = MetaModel::NewObject(AnonymizerService::BATCH_ANONYMIZATION_TASK);
		$oTask->Set('class_to_anonymize', 'Person');
		$oTask->Set('id_to_anonymize', '0');
		$oTask->Set('status', $sInitialStatus);
		$oTask->Set('action', $sInitialAction);
		$oTask->Set('action_params', '');

		$sStatus = $this->InvokeNonPublicMethod(AnonymizerService::class, 'ProcessAnonymizationTask', $oService, [$oTask]);

		$this->assertEquals($sExpectedStatus, $sStatus);
		$this->assertEquals($sExpectedActionParams, $oTask->Get('action_params'));
	}

	public function ProcessAnonymizationTaskProvider()
	{
		return [
			'no action'               => [
				'finished',
				'created',
				'',
				'',
				[],
				[[]],
			],
			'one action finished' => [
				'finished',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
				],
			],
			'one action paused'   => [
				'paused',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => false,
					],
				],
			],
			'one action error'    => [
				'running',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => 'Exception',
					],
				],
			],
			'one action continue' => [
				'finished',
				'paused',
				'\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction',
				' - Task1 execute',
				['\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
				],
			],
			'one action retry on error' => [
				'finished',
				'running',
				'\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction',
				' - Task1 retry - Task1 execute',
				['\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
				],
			],
			'two actions finished' => [
				'finished',
				'created',
				'',
				' - Task1 init - Task1 execute - Task2 init - Task2 execute',
				['\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction', '\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction2'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => true,
					],
					[
						'Init'       => 'Task2 init',
						'Retry'      => 'Task2 retry',
						'Execute'    => 'Task2 execute',
						'ExecReturn' => true,
					],
				],
			],
			'two actions, first paused' => [
				'paused',
				'created',
				'',
				' - Task1 init - Task1 execute',
				['\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction', '\Combodo\iTop\Anonymizer\Test\Service\AnonymizationTestAction2'],
				[
					[
						'Init'       => 'Task1 init',
						'Retry'      => 'Task1 retry',
						'Execute'    => 'Task1 execute',
						'ExecReturn' => false,
					],
					[
						'Init'       => 'Task2 init',
						'Retry'      => 'Task2 retry',
						'Execute'    => 'Task2 execute',
						'ExecReturn' => true,
					],
				],
			],
		];
	}
}
