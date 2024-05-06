<?php
/*
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Test\Service;

use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use DBObjectSet;
use MetaModel;
use ormCaseLog;
use ormLinkSet;
use UserRights;


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
	 * @dataProvider AnonymizationProvider
	 *
	 * @param $aPerson
	 * @param $aPersonExpected
	 *
	 * @return void
	 * @throws \ReflectionException
	 */
	public function testAnonymization($aPerson, $aPersonExpected)
	{
		UserRights::Login('admin'); // Login as admin
		$aParamsPerson = [
			'name'       => $aPerson['name'],
			'first_name' => $aPerson['first_name'],
			'email'      => $aPerson['email'],
			'org_id'     => '1',
		];
		$oPerson = MetaModel::NewObject('Person');
		foreach ($aParamsPerson as $sAttCode => $oValue) {
			$oPerson->Set($sAttCode, $oValue);
		}
		$oPerson->DBInsert();
		$iPersonId = $oPerson->GetKey();

		//profile
		$sClass = 'URP_UserProfile';
		$oSet = DBObjectSet::FromArray($sClass, []);
		$oLinkSet = new ormLinkSet('UserLocal', 'profile_list', $oSet);
		$oLnk = MetaModel::NewObject($sClass);
		$oLnk->Set('profileid', 2);
		$oLinkSet->AddItem($oLnk);

		$oUser = MetaModel::NewObject('UserLocal');
		$aParamsUser = [
			'password'     => "#AAAA2020b",
			'contactid'    => $iPersonId,
			'login'        => 'loginTest',
			'language'     => "FR FR",
			'profile_list' => $oLinkSet,
		];

		foreach ($aParamsUser as $sAttCode => $oValue) {
			$oUser->Set($sAttCode, $oValue);
		}
		$oUser->DBInsert();

		//get params for UserRequest
		$iUserKey = $oUser->GetKey();
		$sFriendlyName = $oPerson->Get('friendlyname');
		$sName = $oPerson->Get('name');
		$sFirstName = $oPerson->Get('first_name');
		$sEmail = $oPerson->Get('email');

		$sStarFriendlyName = $aPersonExpected['friendlyname'];
		$sStarEmail = $aPersonExpected['email'];
		$sExpectedFriendlyName = "Anonymous Contact $iPersonId";
		$sExpectedEmail = "Anonymous.Contact$iPersonId@anony.mized";

		$aUserRequestForTest = [
			[
				'initial'  => [
					'title'       => "title of user request $sEmail",
					'description' => "description $sFriendlyName and name : $sName and firstname:$sFirstName <\br> email : $sEmail<b>bbbb</b>",
					'private_log' => [
						[
							'message' => "test  $sEmail not replace false friendlyname : $sName $sFirstName and not other things",
							'user_id' => $iUserKey,
						],
						[
							'message' => "test  $sEmail replace friendlyname $sFriendlyName",
							'user_id' => $iUserKey,
						],
						[
							'message'    => "test  replace friendlyname $sFriendlyName",
							'user_id'    => 1,
							'user_login' => 'admin',
						],
					],
				],
				'expected' => [
					'title'       => "title of user request $sExpectedEmail",
					'description' => "<p>description $sExpectedFriendlyName and name : $sName and firstname:$sFirstName  email : $sExpectedEmail<b>bbbb</b></p>",
					'private_log' =>
						[
							[
								'message'    => "test replace friendlyname $sStarFriendlyName",
								'user_login' => 'My first name My last name',
							],
							[
								'message'    => "test $sStarEmail replace friendlyname $sStarFriendlyName",
								'user_login' => $sStarFriendlyName,
							],
							[
								'message'    => "test $sStarEmail not replace false friendlyname : $sName $sFirstName and not other things",
								'user_login' => $sStarFriendlyName,
							],
						],
				],
			],
			[
				'initial'  => [
					'title'       => "title of user request $sEmail",
					'description' => "description $sFriendlyName and name : $sName and firstname:$sFirstName <\br> email : $sEmail<b>bbbb</b>",
					'private_log' => [
						[
							'message' => "test  $sEmail not replace false friendlyname : $sName $sFirstName and not other things ",
							'user_id' => 1,
						],
						[
							'message' => "test  $sEmail replace friendlyname $sFriendlyName",
							'user_id' => 1,
						],
						[
							'message' => "test replace friendlyname $sFriendlyName",
							'user_id' => 1,
						],
					],
				],
				'expected' => [
					'title'       => "title of user request $sEmail",
					'description' => "<p>description $sFriendlyName and name : $sName and firstname:$sFirstName  email : $sEmail<b>bbbb</b></p>",
					'private_log' =>
						[
							[
								'message'    => "test replace friendlyname $sFriendlyName",
								'user_login' => 'My first name My last name',
							],
							[
								'message'    => "test $sEmail replace friendlyname $sFriendlyName",
								'user_login' => 'My first name My last name',
							],
							[
								'message'    => "test $sEmail not replace false friendlyname : $sName $sFirstName and not other things",
								'user_login' => 'My first name My last name',
							],
						],
				],
			],
		];
		foreach ($aUserRequestForTest as $i => $aUserRequest) {
			$aParamsUserRequest = [
				'title'       => $aUserRequest['initial']['title'],
				'description' => $aUserRequest['initial']['description'],
				'impact'      => 1,
				'priority'    => '1',
				'urgency'     => '1',
				'org_id'      => '1',
			];

			$oUserRequest = MetaModel::NewObject('UserRequest');
			foreach ($aParamsUserRequest as $sAttCode => $oValue) {
				$oUserRequest->Set($sAttCode, $oValue);
			}
			$oUserRequest->DBInsert();

			$sJson = json_encode(["items" => $aUserRequest['initial']['private_log']]);
			$oJson = json_decode($sJson);
			$oPrivateLog = ormCaseLog::FromJSON($oJson);
			$oUserRequest->AllowWrite(true);
			$oUserRequest->Set('private_log', $oPrivateLog);
			$oUserRequest->DBWrite();

			$aUserRequestForTest[$i]['id'] = $oUserRequest->GetKey();
		}

		$oService = new AnonymizerService();
		$oService->AnonymizeOneObject('Person', $iPersonId, true);


		foreach ($aUserRequestForTest as $iCurrentUR => $aUserRequest) {
			$oNewUserRequest = MetaModel::GetObject('UserRequest', $aUserRequest['id']);
			$oCaseLogFinal = $oNewUserRequest->Get('private_log');
			$aCaseLogs = $oCaseLogFinal->GetAsArray();

			$this->assertEquals($aUserRequest['expected']['title'], $oNewUserRequest->Get('title'), "Test UR n°$iCurrentUR title");
			$this->assertEquals($aUserRequest['expected']['description'], $oNewUserRequest->Get('description'), "Test UR n°$iCurrentUR  description");

			AnonymizerLog::Debug(json_encode($aCaseLogs));
			foreach ($aUserRequest['expected']['private_log'] as $index => $aLog) {
				$this->assertEquals($aLog['message'], $aCaseLogs[$index]['message'], "Test UR n°$iCurrentUR  message private_log index $index");
				$this->assertEquals($aLog['user_login'], $aCaseLogs[$index]['user_login'], "Test UR n°$iCurrentUR login private_log index $index");
			}
		}
	}

	public function AnonymizationProvider()
	{
		return [
			'classic'  => [
				[
					'name'       => 'MyName',
					'first_name' => 'MyFirstname',
					'email'      => 'aa@bb.cc',
					'user'       => [
						'login' => 'loginTest',
					],
				],
				[
					'friendlyname' => '******************',
					'email'        => '********',
				],
			],
			'with éàù' => [
				[
					'name'       => 'AnéàùName',
					'first_name' => 'AnéàùFirstname',
					'email'      => 'aa@bb.cc',
					'user'       => [
						'login' => 'loginTest',
					],
				],
				[
					'friendlyname' => '******************************',
					'email'        => '********',
				],
			],

		];
	}

}
