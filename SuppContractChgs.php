<?php

/* $Id:  $*/

/*The supplier transaction uses the SuppTrans class to hold the information about the invoice
the SuppTrans class contains an array of Contract objects - containing details of all contract charges
Contract charges are posted to the debit of Work In Progress (based on the account specified in the stock category record of the contract item
This is cleared against the cost of the contract as originally costed - when the contract is closed and any difference is taken to the price variance on the contract */

include('includes/DefineSuppTransClass.php');

/* Session started here for password checking and authorisation level check */
include('includes/session.inc');

$title = _('Contract Charges or Credits');

include('includes/header.inc');

if (isset($_POST['Amount'])) {
	$_POST['Amount'] = filter_currency_input($_POST['Amount']);
}

if (!isset($_SESSION['SuppTrans'])){
	prnMsg(_('Contract charges or credits are entered against supplier invoices or credit notes respectively. To enter supplier transactions the supplier must first be selected from the supplier selection screen, then the link to enter a supplier invoice or credit note must be clicked on'),'info');
	echo '<br /><a href="' . $rootpath . '/SelectSupplier.php?">' . _('Select A Supplier') . '</a>';
	exit;
	/*It all stops here if there aint no supplier selected and invoice/credit initiated ie $_SESSION['SuppTrans'] started off*/
}

/*If the user hit the Add to transaction button then process this first before showing  all contracts on the invoice otherwise it wouldnt show the latest addition*/

if (isset($_POST['AddContractChgToInvoice'])){

	$InputError = False;
	if ($_POST['ContractRef'] == ''){
		$_POST['ContractRef'] = $_POST['ContractSelection'];
	} else{
		$result = DB_query("SELECT contractref
								FROM contracts
								WHERE status=2
									AND contractref='" . $_POST['ContractRef'] . "'",$db);
		if (DB_num_rows($result)==0){
			prnMsg(_('The contract reference entered does not exist as a customer ordered contract. This contract cannot be charged to'),'error');
			$InputError =true;
		} //end if the contract ref entered is not a valid contract
	}//end if a contract ref was entered manually
	if (!is_numeric($_POST['Amount'])){
		prnMsg(_('The amount entered is not numeric. This contract charge cannot be added to the invoice'),'error');
		$InputError = True;
	}

	if ($InputError == False){
		$_SESSION['SuppTrans']->Add_Contract_To_Trans($_POST['ContractRef'], $_POST['Amount'], $_POST['Narrative'],$_POST['AnticipatedCost']);
		unset($_POST['ContractRef']);
		unset($_POST['Amount']);
		unset($_POST['Narrative']);
	}
}

if (isset($_GET['Delete'])){
	$_SESSION['SuppTrans']->Remove_Contract_From_Trans($_GET['Delete']);
}

/*Show all the selected ContractRefs so far from the SESSION['SuppInv']->Contracts array */
if ($_SESSION['SuppTrans']->InvoiceOrCredit=='Invoice'){
		echo '<div class="centre"><p class="page_title_text">' . _('Contract charges on Invoice') . ' ' . $_SESSION['SuppTrans']->SuppReference . ' ' .
			_('From') . ' ' . $_SESSION['SuppTrans']->SupplierName . '</p></div>';
} else {
		echo '<div class="centre"><p class="page_title_text">' . _('Contract credits on Credit Note') . ' ' . $_SESSION['SuppTrans']->SuppReference . ' '
			._('From') . ' ' . $_SESSION['SuppTrans']->SupplierName . '</p></div>';
}

echo '<table cellpadding="2" class="selection">';
$TableHeader = '<tr><th>' . _('Contract') . '</th>
					<th>' . _('Amount') . '</th>
					<th>' . _('Narrative') . '</th>
					<th>' . _('Anticipated') . '</th>
				</tr>';
echo $TableHeader;

$TotalContractsValue = 0;

foreach ($_SESSION['SuppTrans']->Contracts as $EnteredContract){

	if  ($EnteredContract->AnticipatedCost==true) {
		$AnticipatedCost = _('Yes');
	} else {
		$AnticipatedCost = _('No');
	}
	echo '<tr><td>' . $EnteredContract->ContractRef . '</td>
		<td class="number">' . locale_money_format($EnteredContract->Amount,$_SESSION['SuppTrans']->CurrCode) . '</td>
		<td>' . $EnteredContract->Narrative . '</td>
		<td>' . $AnticipatedCost . '</td>
		<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?Delete=' . $EnteredContract->Counter . '">' . _('Delete') . '</a></td></tr>';

	$TotalContractsValue += $EnteredContract->Amount;

}

echo '<tr>
	<td  class="number">' . _('Total') . ':</font></td>
	<td class="number">' . locale_money_format($TotalContractsValue,$_SESSION['SuppTrans']->CurrCode) . '</td>
</tr>
</table>';

if ($_SESSION['SuppTrans']->InvoiceOrCredit == 'Invoice'){
	echo '<br /><div class="centre"><a href="' . $rootpath . '/SupplierInvoice.php">' . _('Back to Invoice Entry') . '</a></div><br />';
} else {
	echo '<br /><div class="centre"><a href="' . $rootpath . '/SupplierCredit.php">' . _('Back to Credit Note Entry') . '</a></div><br />';
}

/*Set up a form to allow input of new Contract charges */
echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

if (!isset($_POST['ContractRef'])) {
	$_POST['ContractRef']='';
}
echo '<table class="selection">';
echo '<tr><td>' . _('Contract Reference') . ':</td>
	<td><input type="text" name="ContractRef" size="22" maxlength="20" value="' .  $_POST['ContractRef'] . '" /></td></tr>';
echo '<tr><td>' . _('Contract Selection') . ':<br /><font size="1">' . _('If you know the code enter it above') . '<br />' . _('otherwise select the contract from the list') . '</font></td><td><select name="ContractSelection">';

$sql = "SELECT contractref,
				name
			FROM contracts
			INNER JOIN debtorsmaster
				ON contracts.debtorno=debtorsmaster.debtorno
			WHERE status=2"; //only show customer ordered contracts not quotes or contracts that are finished with

$result = DB_query($sql, $db);

while ($myrow = DB_fetch_array($result)) {
	if (isset($_POST['ContractSelection']) and $myrow['contractref']==$_POST['ContractSelection']) {
		echo '<option selected="True" value="' . $myrow['contractref'] . '">' . $myrow['contractref'] . ' - ' . $myrow['name'] . '</option>';
	} else {
		echo '<option value="' . $myrow['contractref'] . '">' . $myrow['contractref'] . ' - ' . $myrow['name'] . '</option>';
	}
}

echo '</select></td></tr>';

if (!isset($_POST['Amount'])) {
	$_POST['Amount']=0;
	$_POST['Narrative']='';
}
echo '<tr><td>' . _('Amount') . ':</td>
	<td><input type="text" class="number" name="Amount" size="12" maxlength="11" value="' .  locale_money_format($_POST['Amount'], $_SESSION['SuppTrans']->CurrCode) . '" /></td></tr>';
echo '<tr><td>' . _('Narrative') . ':</td>
	<td><input type="text" name="Narrative" size="42" maxlength="40" value="' .  $_POST['Narrative'] . '" /></td></tr>';
echo '<tr><td>' . _('Aniticpated Cost') . ':</td>
	<td>';
if (isset($_POST['AnticipatedCost']) AND $_POST['AnticipatedCost']==1){
	echo '<input type="checkbox" name="AnticipatedCost" checked="True" />';
} else {
	echo '<input type="checkbox" name="AnticipatedCost" />';
}

echo '</td></tr></table><br />';

echo '<div class="centre"><button type="submit" name="AddContractChgToInvoice">' . _('Enter Contract Charge') . '</button></div>';

echo '</form>';
include('includes/footer.inc');
?>