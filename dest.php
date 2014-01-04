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

	if(isset($_POST["submit_start_end"] )) {

		print '
			<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>	
			<script src="http://code.highcharts.com/highcharts.js"></script>
			<script src="http://code.highcharts.com/modules/exporting.js"></script>';
		$opts = array();
		$opts['start'] = $_POST['start'];
		$opts['end'] = $_POST['end'];
		$out_list = nfsend_query("dest::create_graph", $opts);
		$start_date = DateTime::createFromFormat('Y-m-d|', $_POST['start']);
		$end_date = DateTime::createFromFormat('Y-m-d|', $_POST['end']);
		$end_date->add(new DateInterval("P1D"));
	
		$all_dates = new DatePeriod( $start_date, new DateInterval('P1D'), $end_date );
		$dates_in_str = array();
		foreach ($all_dates as $a_date) {
			array_push($dates_in_str, $a_date->format('Y-m-d'));
		}
		echo "
			<script>
			$(function () {
			$('#dest_container').highcharts({
			chart: {
				type: 'area'
			},
			title: {
				text: 'Historic and Estimated Worldwide Population Growth by Region'						},
			subtitle: {
				text: 'Source: Wikipedia.org'
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
		foreach ($out_list as $key => $value) {
			echo "{ name: '". $key . "', data: [";
			echo join(", " , $value);
			echo ']}';
			if(!last($out_list, $key)) {
				echo ',';
			}
		}
		echo "]";

		echo "        });
	    });</script>";
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
		Start date: <input type="date" name="start" value="2014-01-02"/>
		End date: <input type="date" name="end" value="2014-01-03"  />
		<input type="submit" name="submit_start_end" />
		</form>
		';
	echo "<div id=\"dest_container\" style=\"min-width: 310px; height: 400px; margin: 0 auto\"></div>";

} // End of dest_Run

?>
