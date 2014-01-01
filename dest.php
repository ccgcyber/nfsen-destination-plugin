<?php

/*
 * Frontend plugin: dest
 *
 * Required functions: dest_ParseInput and dest_Run
 *
 */

/* 
 * dest_ParseInput is called prior to any output to the web browser 
 * and is intended for the plugin to parse possible form data. This 
 * function is called only, if this plugin is selected in the plugins tab. 
 * If required, this function may set any number of messages as a result 
 * of the argument parsing.
 * The return value is ignored.
 */
function dest_ParseInput( $plugin_id ) {

        SetMessage('error', "Error set by demo plugin!");
        SetMessage('warning', "Warning set by demo plugin!");
        SetMessage('alert', "Alert set by demo plugin!");
	SetMessage('info', "Info set by demo plugin!");
	if(isset($_POST["submit_start_end"] )) {
		$opts = array();
		$opts['start'] = $_POST['start'];
		$opts['end'] = $_POST['end'];
		$out_list = nfsend_query("dest::create_graph", $opts);
		SetMessage('info', "POST is set");
	} else {
		SetMessage('info', "POST is not set");
	}

} // End of dest_ParseInput


/*
 * This function is called after the header and the navigation bar have 
 * been sent to the browser. It's now up to this function what to display.
 * This function is called only, if this plugin is selected in the plugins tab
 * Its return value is ignored.
 */
function dest_Run( $plugin_id ) {
	echo '
		<form method="post" action="/nfsen/nfsen.php"  >
		Start date: <input type="date" name="start" value="2014-01-01"/>
		End date: <input type="date" name="end" value="2014-01-01"  />
		<input type="submit" name="submit_start_end" />
		</form>

		';

} // End of dest_Run

?>
