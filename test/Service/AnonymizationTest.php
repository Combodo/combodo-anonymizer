<?php
/*
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Test\Service;

use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use DBObjectSet;
use HTMLSanitizer;
use MetaModel;
use ormCaseLog;
use ormLinkSet;
use UserRights;
use utils;


/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class AnonymizationTest extends ItopDataTestCase
{
	const USE_TRANSACTION = true;
	const CREATE_TEST_ORG = false;
	private $TEST_LOG_FILE;

	protected function setUp(): void
	{
		parent::setUp();
	//	require_once 'AnonymizationTestActionFactory.php';
	//	require_once 'AnonymizationTestAction.php';
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
	 * @param $sExpectedAction
	 * @param $sCurrentAction
	 * @param $aActions
	 *
	 * @return void
	 * @throws \ReflectionException
	 */
	public function testAnonymization()
	{
		UserRights::Login('admin'); // Login as admin
		$aParamsPerson = [
			'name'       =>  'AnéàùName',
			'first_name'       => 'AnéàùFirstname',
			'email'    => 'aa@bb.cc',
			'org_id'     => '1',
		];
		$aParamsUserRequest = [
			'title'     => 'title of user request',
			'description'     =>  'description AnéàùName AnéàùFirstname <\br> aa@bb.cc<b>bbbb</b>',
			'impact'     => 1,
			'priority'     => '1',
			'urgency'   => '1',
			'org_id'     => '1',
		];
		$oPerson = MetaModel::NewObject('Person');
		foreach ($aParamsPerson as $sAttCode => $oValue) {
			$oPerson->Set($sAttCode, $oValue);
		}
		$oPerson->DBInsert();
		$iPersonKey = $oPerson->GetKey();

		//profile
		$sClass = 'URP_UserProfile';
		$oSet = DBObjectSet::FromArray($sClass, []);
		$oLinkSet = new ormLinkSet('UserLocal', 'profile_list', $oSet);
		$oLnk = MetaModel::NewObject($sClass);
		$oLnk->Set('profileid', 2);
		$oLinkSet->AddItem($oLnk);

		$oUser = MetaModel::NewObject('UserLocal');
		$aParamsUser = [
			'password' =>"#AAAA2020b",
			'contactid' =>$iPersonKey ,
			'login' => 'loginTest',
			'language' => "FR FR",
			'profile_list' => $oLinkSet,
		];

		foreach ($aParamsUser as $sAttCode => $oValue) {
			$oUser->Set($sAttCode, $oValue);
		}
		$oUser->DBInsert();
		$iUserKey = $oUser->GetKey();

		$oUserRequest = MetaModel::NewObject('UserRequest');
		foreach ($aParamsUserRequest as $sAttCode => $oValue) {
			$oUserRequest->Set($sAttCode, $oValue);
		}
		$oUserRequest->DBInsert();
		$iUserRequestKey = $oUserRequest->GetKey();

		$aItems = [
			[
				'message' => 'test  aa@bb.cc replace friendlyname : AnéàùFirstname AnéàùName and not other things AnéàùName ',
				'user_id' => $iUserKey,
			],
			[
				'message' => 'test  aa@bb.cc replace friendlyname',
				'user_id' => 1,
			],
			[
				'message' => 'test  replace friendlyname AnéàùName AnéàùFirstname',
				'user_id' => 1,
			]
		];

		$sJson = json_encode(["items" => $aItems]);
		$oJson = json_decode($sJson);
		$oPrivateLog = ormCaseLog::FromJSON($oJson);
		$oUserRequest->AllowWrite(true);
		$oUserRequest->Set('private_log', $oPrivateLog);
		$oUserRequest->DBWrite();

		$oService = new AnonymizerService();
		$oService->AnonymizeOneObject('Person', $iPersonKey, true);
		$oNewUserRequest = MetaModel::GetObject('UserRequest',$iUserRequestKey);

		$oCaseLogFinal = $oNewUserRequest->Get('private_log');
		$aCaseLogs=$oCaseLogFinal->GetAsArray();

		AnonymizerLog::Debug( json_encode($aCaseLogs));
		$this->assertEquals($aCaseLogs[0]['message'], 'toto');
	}

	public function CleanupCaselogProvider()
	{
		return [
			'empty' => [null, null, []],
			'first' => ['action1', null, ['action1', 'action2']],
			'2nd' => ['action2', 'action1', ['action1', 'action2']],
			'last' => [null, 'action2', ['action1', 'action2']],
		];
	}

}
