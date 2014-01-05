<?php

/*
 * Frontend plugin: dest
 *
 * Required functions: dest_ParseInput and dest_Run
 *
 */

function last($array, $key) {
	end($array);
	return $key === key($array);
}

/* 
 * dest_ParseInput is called prior to any output to the web browser 
 * and is intended for the plugin to parse possible form data. This 
 * function is called only, if this plugin is selected in the plugins tab. 
 * If required, this function may set any number of messages as a result 
 * of the argument parsing.
 * The return value is ignored.
 */
function dest_ParseInput( $plugin_id ) {
	if(!isset($_POST["{$plugin_id}_submit_start_end"] )) {
		return;
	}
	$_SESSION['refresh'] = 0;
	print '
		<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>	
		<script src="http://code.highcharts.com/highcharts.js"></script>
		<script src="http://code.highcharts.com/modules/exporting.js"></script>';
	$opts = array();
	$opts['start'] = $_POST["{$plugin_id}_start"];
	$opts['end'] = $_POST["{$plugin_id}_end"];
	$graph_data_from_backend = nfsend_query("dest::create_graph", $opts);
	if(!is_array($graph_data_from_backend)) {
		SetMessage('error', "BackEnd returned null");
		return;
	}
	$start_date = DateTime::createFromFormat('Y-m-d|', $_POST["{$plugin_id}_start"]);
	$end_date = DateTime::createFromFormat('Y-m-d|', $_POST["{$plugin_id}_end"]);
	$end_date->add(new DateInterval("P1D"));
	
	$all_dates = new DatePeriod( $start_date, new DateInterval('P1D'), $end_date );
	$dates_in_str = array();
	foreach ($all_dates as $a_date) {
		array_push($dates_in_str, str_replace(' ','',"'". $a_date->format('Y-m-d'))."'");
	}
	echo "
	<script>
		$(function () {
			$('#{$plugin_id}_container').highcharts({
			chart: {
				type: 'area'
			},
			title: {
				text: 'Top 10 domain destinations'						},
			subtitle: {
				text: 'Source: Top 100 ip addresses'
			},
			xAxis: {
				categories: 	
	";
	echo "[". join(', ', $dates_in_str) . "]";
	echo ", tickmarkPlacement: 'on',
            title: {
              enabled: false
            }
          },
          yAxis: {
            title: {
             text: 'Megabytes'
          },
                labels: {
                    formatter: function() {
                        return this.value / 1000000;
                    }
                }
            },
            tooltip: {
                shared: true,
                valueSuffix: ' MBs'
            },
            plotOptions: {
                area: {
                    stacking: 'normal',
                    lineColor: '#666666',
                    lineWidth: 1,
                    marker: {
                        lineWidth: 1,
                        lineColor: '#666666'
                    }
                }
            },";	
	echo "series: [";
	foreach ($graph_data_from_backend as $key => $value) {
		echo "{ name: '". $key . "', data: [";
		echo join(", " , $value);
		echo ']}';
		if(!last($graph_data_from_backend, $key)) {
			echo ',';
		}
	}
	echo "]";

	echo "        });
    });</script>";
	SetMessage('info', "Query succeeded!");
} // End of dest_ParseInput


/*
 * This function is called after the header and the navigation bar have 
 * been sent to the browser. It's now up to this function what to display.
 * This function is called only, if this plugin is selected in the plugins tab
 * Its return value is ignored.
 */
function dest_Run( $plugin_id ) {
	$start_date = '2014-01-02';
	$end_date = '2014-01-03';
	if(isset($_POST["{$plugin_id}_submit_start_end"] )) {
		$start_date = $_POST["{$plugin_id}_start"];
		$end_date = $_POST["{$plugin_id}_end"];
	} 
	echo "
		<form method=\"post\"   >
		Start date: <input type=\"date\" name=\"{$plugin_id}_start\" value=\"{$start_date}\"/>
	End date: <input type=\"date\" name=\"{$plugin_id}_end\" value=\"$end_date\"/>
		<input type=\"submit\" name=\"{$plugin_id}_submit_start_end\" />
		</form>
		";
	echo "<div id=\"{$plugin_id}_container\" style=\"min-width: 310px; height: 400px; margin: 0 auto\"></div>";


} // End of dest_Run

?>
