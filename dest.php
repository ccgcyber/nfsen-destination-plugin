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
	$graph_id = 0;
	if(isset($_GET['1_graph'])) {
		$graph_id = intval($_GET['1_graph']);
	}
	print "<!-- HERE: $graph_id -->";

	$graph_id_to_table_name = array(
		"tcp_flows", 
		"tcp_packets" , 
		"tcp_bytes", 
		"udp_flows",
		"udp_packets", 
		"udp_bytes"
	);

	$table_name = $graph_id_to_table_name[$graph_id];
	
	mysql_select_db("dest");
	$query = "select timeslot from rrdgraph_$table_name group by timeslot order by timeslot;";
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
                        return this.value;
                    }
                }
            },
            tooltip: {
                shared: false,
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
	
	$query = "select domain from rrdgraph_$table_name group by domain;";
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
			$current_date[$x] = "null";
		}
		$domain_query = "select timeslot, frequency from rrdgraph_$table_name where domain='$domain_name'";
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

	echo <<< EOT
<table style="text-align: left;" border="0" cellpadding="3" cellspacing="2">
    <tbody>
        <tr>
            <td>
                <table style="text-align: left;" border="0" cellpadding="0" cellspacing="3">
                    <tbody>
                        <tr>
                            <td>TCP Packets</td>
                            <td>TCP Bytes</td>
                            <td>TCP Flows</td>
                            <td>UDP Packets</td>
                            <td>UDP Bytes</td>
                            <td>UDP Flows</td>
                        </tr>
                        <tr>
                            <td><a href='/toolkit/gui/nfsen/nfsen.php?1_graph=1'> <img src=rrdgraph.php?cmd=PortTracker::get-portgraph&profile=./live&arg=tcp+packets+0+0+1+1393553100+1394157900+139-445-80-443-88-8194-389-51910-5223-135+-+80 border='0' width='165' height='81' alt='tcp-packets'></a>

                            </td>
                            <td><a href='/toolkit/gui/nfsen/nfsen.php?1_graph=2'> <img src=rrdgraph.php?cmd=PortTracker::get-portgraph&profile=./live&arg=tcp+bytes+0+0+1+1393553100+1394157900+139-445-80-443-8530-88-8194-389-57184-51910+-+80 border='0' width='165' height='81' alt='tcp-bytes'></a>

                            </td>
                            <td><a href='/toolkit/gui/nfsen/nfsen.php?1_graph=0'> <img src=rrdgraph.php?cmd=PortTracker::get-portgraph&profile=./live&arg=tcp+flows+0+0+1+1393553100+1394157900+80-5223-443-445-88-8194-1179-5222-389-135+-+80 border='0' width='165' height='81' alt='tcp-flows'></a>

                            </td>
                            <td><a href='/toolkit/gui/nfsen/nfsen.php?1_graph=4'> <img src=rrdgraph.php?cmd=PortTracker::get-portgraph&profile=./live&arg=udp+packets+0+0+1+1393553100+1394157900+61724-137-47808-6343-1320-53-2008-162-61745-1812+-+80 border='0' width='165' height='81' alt='udp-packets'></a>

                            </td>
                            <td><a href='/toolkit/gui/nfsen/nfsen.php?1_graph=5'> <img src=rrdgraph.php?cmd=PortTracker::get-portgraph&profile=./live&arg=udp+bytes+0+0+1+1393553100+1394157900+6343-61724-1320-137-47808-88-53-1812-22610-138+-+80 border='0' width='165' height='81' alt='udp-bytes'></a>

                            </td>
                            <td><a href='/toolkit/gui/nfsen/nfsen.php?1_graph=3'> <img src=rrdgraph.php?cmd=PortTracker::get-portgraph&profile=./live&arg=udp+bytes+0+0+1+1393553100+1394157900+6343-61724-1320-137-47808-88-53-1812-22610-138+-+80 border='0' width='165' height='81' alt='udp-bytes'></a>

                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="vertical-align: bottom;">
                <table>
                    <tr>
                        <td>

			    <div id="{$plugin_id}_moving_graph" style="min-width: 669px; height: 496px; margin: 0 auto"></div>
                        </td>
                        <td style="vertical-align: top;">
                            <table>
                                <tr>
                                    <td>
                                        <form action="/toolkit/gui/nfsen/nfsen.php" method="POST">Show Top&nbsp;
                                            <select name='1_topN' onchange='this.form.submit();' size=1>
                                                <option value='0'>0
                                                    <option value='1'>1
                                                        <option value='2'>2
                                                            <option value='3'>3
                                                                <option value='4'>4
                                                                    <option value='5'>5
                                                                        <option value='6'>6
                                                                            <option value='7'>7
                                                                                <option value='8'>8
                                                                                    <option value='9'>9
                                                                                        <option value='10' selected>10</select>Domains</form>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <form action="/toolkit/gui/nfsen/nfsen.php" method="POST">
                                            <input type="radio" onClick='this.form.submit();' name='1_24avg' value="0" checked>now&nbsp;
                                            <input type="radio" onClick='this.form.submit();' name='1_24avg' value="1">24 hours</td>
                                    </form>
                                </tr>
                                <tr>
                                    <td style='padding-top:20px;'>Track Domains:
                                        <br>
                                        <form action="/toolkit/gui/nfsen/nfsen.php" method="POST">
                                            <select name='1_track' style='width:100%;padding-bottom:20px;' size=2></select>
                                            <p>
                                                <input type='text' name='1_trackport' value='' size='5' maxlength='5'>
                                                <input type='submit' name='1_action' value='Add'>
                                                <input type='submit' name='1_action' value='Delete'>
                                        </form>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='padding-top:20px;'>Skip Domains:
                                        <br>
                                        <form action="/toolkit/gui/nfsen/nfsen.php" method="POST">
                                            <select name='1_skip' style='width:100%;padding-bottom:20px;' size=2>
                                                <option value='80'>80</select>
                                            <p>
                                                <input type='text' name='1_skipport' value='' size='5' maxlength='5'>
                                                <input type='submit' name='1_action' value='Add'>
                                                <input type='submit' name='1_action' value='Delete'>
                                        </form>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- <tr>
                        <td>
                         <table style='width:100%;'>
                                <tr>
                                    <td style='padding-top:0px;'>
                                        <form action="/toolkit/gui/nfsen/nfsen.php" method="POST" style="display:inline">Display
                                            <select name='1_wsize' onchange='this.form.submit();' size=1>
                                                <option value='0'>12 Hours</option>
                                                <option value='1'>1 day</option>
                                                <option value='2'>2 days</option>
                                                <option value='3'>4 days</option>
                                                <option value='4' selected>1 week</option>
                                                <option value='5'>2 weeks</option>
                                            </select>
                                    </td>
                                    <td>Y-axis:
                                        <input type="radio" onClick='this.form.submit();' name='1_logscale' value="0" checked>Linear
                                        <input type="radio" onClick='this.form.submit();' name='1_logscale' value="1">Log</td>
                                    <td>Type:
                                        <input type="radio" onClick='this.form.submit();' name='1_stacked' value="1">Stacked
                                        <input type="radio" onClick='this.form.submit();' name='1_stacked' value="0" checked>Line</form>
                                </tr>
                            </table>
                            </td>
                            <td>
                                <td></td>
                    </tr>-->
                </table>
            </td>
        </tr>
    </tbody>
</table>

EOT;



} // End of dest_Run

?>
