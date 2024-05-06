/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

function AnonymizeAListOfPersons(sSerializedFilter, iCount)
{
	var sLabel = Dict.S('Anonymization:OnePersonWarning');

	if (iCount > 1) {
		var sTemplate = Dict.S('Anonymization:ListOfPersonsWarning');
		sLabel = sTemplate.replace('%d', iCount);
	}
	AnonymizationConfirmDialog(sLabel, function () {
		const oInProgressModal = $('<h1 id="in-progress-modal-message"><img src="../images/indicator.gif" /> '+Dict.S('Anonymization:InProgress')+'</h1>');
		$('body').append(oInProgressModal);
		oInProgressModal.dialog({
			modal: true,
			close: function () {
				oInProgressModal.remove();
			}
		});
		$.ajax({
			method: "POST",
			url: GetAbsoluteUrlAppRoot()+'/pages/exec.php?exec_module=combodo-anonymizer&exec_page=ajax.php',
			data: {
				operation: 'AnonymizeList',
				filter: sSerializedFilter
			},
			success: function(data) {
				$('body').append(data);
				oInProgressModal.dialog('close');
			},
			error: function(jqXHR) {
				$('#in-progress-modal-message').html(jqXHR.responseText);
			}
		});

	});
}

function AnonymizeOnePerson(iPersonId) {
	var sLabel = Dict.S('Anonymization:OnePersonWarning');
	AnonymizationConfirmDialog(sLabel, function () {
		const oInProgressModal = $('<h1 id="in-progress-modal-message"><img src="../images/indicator.gif" /> '+Dict.S('Anonymization:InProgress')+'</h1>');
		$('body').append(oInProgressModal);
		oInProgressModal.dialog({
			modal: true,
			close: function () {
				oInProgressModal.remove();
			}
		});
		$.ajax({
			method: "POST",
			url: GetAbsoluteUrlAppRoot()+'/pages/exec.php?exec_module=combodo-anonymizer&exec_page=ajax.php',
			data: {
				operation: 'AnonymizeOne',
				id: iPersonId
			},
			success: function(data) {
				$('body').append(data);
				oInProgressModal.dialog('close');
			},
			error: function(jqXHR) {
				$('#in-progress-modal-message').html(jqXHR.responseText);
			}
		});
	});
}

function AnonymizationConfirmDialog(sLabel, fnAction)
{
	var sOkLabel = Dict.S('UI:Button:Ok');
	var sCancelLabel = Dict.S('UI:Button:Cancel');
	var sTitle = Dict.S('Anonymization:Confirmation');

	var jDlg = $('<div>'+sLabel+'</div>');
	$('body').append(jDlg);
	jDlg.dialog({
		title: sTitle,
		width: 500,
		autoOpen: true,
		modal: true,
		close: function() { jDlg.remove(); },
		buttons: [
		{ text: sCancelLabel, click: function() { jDlg.dialog('close'); } },
		{ text: sOkLabel, click: function() { jDlg.dialog('close'); fnAction(); } }
		]
	});
}

function AnonymizationDialog(sTitle, sLabel)
{
	var sCloseLabel = Dict.S('Anonymization:Close');

	$.unblockUI();

	var jDlg = $('<div>'+sLabel+'</div>');
	$('body').append(jDlg);
	jDlg.dialog({
		title: sTitle,
		width: 500,
		autoOpen: true,
		modal: true,
		close: function() { jDlg.remove(); },
		buttons: [
		{ text: sCloseLabel, click: function() { jDlg.dialog('close'); } }
		]
	});
}

function AnonymizationUpdateFormInputs()
{
	var bAnonymizeChecked = $('#checkbox_anonymize').prop('checked');
	var bPurgeChecked = $('#checkbox_purge').prop('checked');
	
	$('#anonymization_delay').prop('disabled', !bAnonymizeChecked);
	$('#notifications_purge_delay').prop('disabled', !bPurgeChecked);
	
}