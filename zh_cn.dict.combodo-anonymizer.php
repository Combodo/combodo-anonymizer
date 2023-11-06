<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	// Dictionary entries go here
	'combodo-anonymizer/Operation:DisplayConfig/Title' => '匿名化',
	'combodo-anonymizer/Operation:ApplyConfig/Title' => '匿名化',
	'Anonymization:AnonymizeAll' => '全部匿名化处理',
	'Anonymization:AnonymizeOne' => '匿名化处理',
	'Anonymization:OnePersonWarning' => '确认你要匿名化处理这名人员吗? (此操作不能回退)',
	'Anonymization:ListOfPersonsWarning' => '确认你要匿名化处理这%d名人员吗? (此操作不能回退)',
	'Anonymization:Confirmation' => '请确认',
	'Anonymization:Information' => '信息',
	'Anonymization:RefreshTheList' => '刷新列表以查看匿名化处理结果...',
	'Anonymization:DoneOnePerson' => '此联系人已被匿名化处理...',
	'Anonymization:InProgress' => '匿名化处理中...',
	'Anonymization:Success' => '匿名化成功',
	'Anonymization:Error' => '匿名化失败',
	'Anonymization:Close' => '关闭',
	'Anonymization:Configuration' => '配置',
	'Menu:ConfigAnonymizer' => '匿名化',
	'Menu:AnonymizationTask' => '匿名化任务',
	'Menu:AnonymizationTask+' => '匿名化任务',
	'Anonymization:AutomationParameters' => '自动匿名化',
	'Anonymization:AnonymizationDelay_Input' => '自动匿名化处理标记为已废弃超过%1$s天的人员.',

	'Anonymization:Configuration:TimeRange' => '允许操作的时间范围',
	'Anonymization:Configuration:time' => '开始时间 (HH:MM)',
	'Anonymization:Configuration:end_time' => '结束时间 (HH:MM)',
	'Anonymization:Configuration:Weekdays' => '周天',
	'Anonymization:Configuration:Weekday:monday' => '周一',
	'Anonymization:Configuration:Weekday:tuesday' => '周二',
	'Anonymization:Configuration:Weekday:wednesday' => '周三',
	'Anonymization:Configuration:Weekday:thursday' => '周四',
	'Anonymization:Configuration:Weekday:friday' => '周为',
	'Anonymization:Configuration:Weekday:saturday' => '周六',
	'Anonymization:Configuration:Weekday:sunday' => '周日',

	// Default values used during anonymization
	'Anonymization:Person:name' => '联系人~~',
	'Anonymization:Person:first_name' => '匿名的~~',
	'Anonymization:Person:email' => '%1$s.%2$s%3$s@anony.mized',
));

//
// Class: Person
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:Person/Attribute:anonymized' => '已匿名',
	'Class:Person/Attribute:anonymized+' => '~~',
));

//
// Class: RessourceAnonymization
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:RessourceAnonymization' => '资源匿名化',
	'Class:RessourceAnonymization+' => '~~',
));

//
// Class: AnonymizationTaskAction
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:AnonymizationTaskAction' => '匿名化任务操作',
	'Class:AnonymizationTaskAction+' => '~~',
	'Class:AnonymizationTaskAction/Attribute:action_params' => '操作参数',
	'Class:AnonymizationTaskAction/Attribute:action_params+' => '~~',
));

//
// Class: AnonymizationTask
//

Dict::Add('ZH CN', 'Chinese', '简体中文', array(
	'Class:AnonymizationTask' => '匿名化任务',
	'Class:AnonymizationTask+' => '~~',
	'Class:AnonymizationTask/Attribute:person_id' => '人员',
	'Class:AnonymizationTask/Attribute:person_id+' => '~~',
	'Class:AnonymizationTask/Attribute:anonymization_context' => '匿名化上下文',
	'Class:AnonymizationTask/Attribute:anonymization_context+' => '~~',
));

// Additional language entries not present in English dict
Dict::Add('ZH CN', 'Chinese', '简体中文', array(
 'Class:AnonymizationTask/Attribute:class_to_anonymize' => '要匿名化处理的类型',
 'Class:AnonymizationTask/Attribute:class_to_anonymize+' => '~~',
 'Class:AnonymizationTask/Attribute:id_to_anonymize' => '要匿名化处理的对象',
 'Class:AnonymizationTask/Attribute:id_to_anonymize+' => '~~',
 'Anonymization:NotificationsPurgeParameters' => '自动清除通知',
 'Anonymization:PurgeDelay_Input' => '自动删除所有%1$s天前生成的通知.',
));
