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
	print '
		<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>	
		<script src="http://code.highcharts.com/highcharts.js"></script>
		<script src="http://code.highcharts.com/modules/exporting.js"></script>';
	$link = mysql_connect('localhost', 'dester', 'passwder');
	if (!$link) {
		die('Could not connect: ' . mysql_error());
	}
	mysql_select_db("dest");
	$query = "select timeslot from rrdgraph group by timeslot order by timeslot;";
	$result = mysql_query($query);

	if (!$result) {
   		$message  = 'Invalid query: ' . mysql_error() . "\n";
    		$message .= 'Whole query: ' . $query;
    		die($message);
	}

	$dates_to_index = array();	

	echo "
	<script>
		$(function () {
			$('#{$plugin_id}_moving_graph').highcharts({
			chart: {
				type: 'area'
			},
			title: {
				text: 'Top 10 domain destinations'						},
			subtitle: {
				text: 'Source: Top 100 ip addresses'
			},
			xAxis: {
				categories: 	[
	";
	for ($x = 0; $x < mysql_num_rows($result); $x++){
		$row = mysql_fetch_assoc($result);
		$dtime = $row['timeslot'];
		$dates_to_index[$dtime] = $x;
		echo "'". substr($dtime, -4, 2) . ":". substr($dtime, -2) . "'"; 
		if($x + 1 != mysql_num_rows($result)){
			echo ", ";
		}  
	}
	echo "]";
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
	
	$query = "select domain from rrdgraph group by domain;";
	$result = mysql_query($query);

	if (!$result) {
   		$message  = 'Invalid query: ' . mysql_error() . "\n";
    		$message .= 'Whole query: ' . $query;
    		die($message);
	}
	
	$current_row_id = 0;	
	$numResults = mysql_num_rows($result);

	while($row = mysql_fetch_row($result)) {
		$domain_name = $row[0];	
		echo "{ name: '". $domain_name . "', data: [";
		$current_date = array();
		for($x = 0; $x < count($dates_to_index); $x++) {
			$current_date[$x] = 0;
		}
		$domain_query = "select timeslot, frequency from rrdgraph where domain='$domain_name'";
		$domain_result=mysql_query($domain_query);	
		if (!$domain_result) {
   			$message  = 'Invalid query: ' . mysql_error() . "\n";
    			$message .= 'Whole query: ' . $domain_query;
    			die($message);
		}
		while(($current_row = mysql_fetch_row($domain_result))) {
			$current_date[$dates_to_index[$current_row[0]]] = $current_row[1];
		}

		echo implode(", ", $current_date);
		echo ']}';
		if(++$current_row_id != $numResults){ 
			echo ', ';
		}	
	}
	echo "]";
	echo "        });
    });</script>";
	mysql_close($link);
	if(!isset($_POST["{$plugin_id}_submit_start_end"] )) {
		return;
	}
	$_SESSION['refresh'] = 0;
	$opts = array();
	$opts['start'] = $_POST["{$plugin_id}_start"];
	$opts['end'] = $_POST["{$plugin_id}_end"];
	$dates_from_backend = nfsend_query("dest::get_dates", $opts);

	$dates_in_str = array();
	for($i = 0; $i < count($dates_from_backend); $i++) {
		$one = $dates_from_backend["$i"];
		array_push($dates_in_str, "'$one'");		
	}

	$graph_data_from_backend = nfsend_query("dest::create_graph", $opts);
	if(!is_array($graph_data_from_backend)) {
		SetMessage('error', "BackEnd returned null");
		return;
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

	echo "        });});</script>";
	SetMessage('info', "Query succeeded!");
} // End of dest_ParseInput


/*
 * This function is called after the header and the navigation bar have 
 * been sent to the browser. It's now up to this function what to display.
 * This function is called only, if this plugin is selected in the plugins tab
 * Its return value is ignored.
 */
function dest_Run( $plugin_id ) {
/**
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
	echo "<div id=\"{$plugin_id}_container\" style=\"min-width: 310px; height: 400px; margin: 0 auto\"></div>";*/
	echo "<div id=\"{$plugin_id}_moving_graph\" style=\"min-width: 310px; height: 400px; margin: 0 auto\"></div>";


} // End of dest_Run

?>
