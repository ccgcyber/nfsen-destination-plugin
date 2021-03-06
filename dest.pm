package dest;

use strict;
use warnings;
use Socket;
use Sys::Syslog;
use Sys::Syslog qw(:standard :macros);
use Data::Dumper;
use DateTime;
use Net::Subnet;
use String::Util 'trim';
use DBI;
use DateTime::Format::MySQL;
use Net::Whois::IP qw(whoisip_query);
use Net::Patricia;


our $VERSION = 100;

our % cmd_lookup = (
	"create_graph" => \&CreateGraph,
	"get_dates" => \&GetDates
);

sub GetDates {
	my $socket = shift;
	my $opts = shift;

	my @start_date = split("-", $$opts{'start'});
	my @end_date = split("-", $$opts{'end'});	
	my $sdate = DateTime->new(
		            year => $start_date[0],
			       month => $start_date[1],
				     day => $start_date[2],
			  time_zone  => 'floating',
	 );

	my $edate = DateTime->new(
		            year => $end_date[0],
			       month => $end_date[1],
				     day => $end_date[2],
			  time_zone  => 'floating',
	);
	$edate->add(days=>1);
	my %dates_of_interest;
	my $i = 0;
	for(my $curr_date = $sdate->clone(); DateTime->compare($curr_date, $edate) == -1; $curr_date->add(days=>1)) {
		my $curr_date_string = $curr_date->ymd;
		$dates_of_interest{"$i"} = "$curr_date_string";
		$i += 1;
	}
	Nfcomm::socket_send_ok ($socket, \%dates_of_interest);
}

sub CreateGraph {
    my      $socket = shift;
    my      $opts = shift;
       
	my @start_date = split("-", $$opts{'start'});
	my @end_date = split("-", $$opts{'end'});	

	my $sdate = DateTime->new(
		            year => $start_date[0],
			       month => $start_date[1],
				     day => $start_date[2],
			  time_zone  => 'floating',
				 );

	my $edate = DateTime->new(
		            year => $end_date[0],
			       month => $end_date[1],
				     day => $end_date[2],
			  time_zone  => 'floating',
	);
	syslog("info", "Query ran from $sdate to $edate");
	$edate->add(days=>1);
	my @dates_of_interest;

	for(my $curr_date = $sdate->clone(); DateTime->compare($curr_date, $edate) == -1; $curr_date->add(days=>1)) {
		push @dates_of_interest, $curr_date->clone();
	}

	$edate->subtract(days=>1);
	
	my $syear = $sdate->year();
	my $smonth = sprintf("%02d", $sdate->month());
	my $sday = sprintf("%02d", $sdate->day());
	my $eyear = $edate->year();
	my $emonth = sprintf("%02d", $edate->month());
	my $eday = sprintf("%02d", $edate->day());

	my $netflow_sources = trim(`cat /tmp/nfsen_dest_plugin_ipc.txt 2>/dev/null`);
	my $nfdump_command = "$NfConf::PREFIX/nfdump -M $netflow_sources  -T  -R ${syear}/${smonth}/${sday}/nfcapd.${syear}${smonth}${sday}0000:${eyear}/${emonth}/${eday}/nfcapd.${eyear}${emonth}${eday}2355 -n 100 -s dstip/bytes -N -o csv -q | awk 'BEGIN { FS = \",\" } ; { if (NR > 1) print \$5, \$10 }'";
	my @nfdump_output = `$nfdump_command`;
	my %domain_name_to_bytes;
	my %domain_name_to_ip_addresses;

	foreach my $a_line (@nfdump_output) {
		my @ip_address_and_freq = split(" ", $a_line);
		my $arr_size = @ip_address_and_freq;
		if ($arr_size != 2) { 
			next;
		}
		my $ip_address = trim($ip_address_and_freq[0]);
		my @classes = split(/\./, $ip_address);
		if ($classes[0] eq "10" or ($classes[0] eq "192" and $classes[1] eq "168") ) {
			next;	
		}
		my $host_name = gethostbyaddr(inet_aton($ip_address), AF_INET);
		my $frequency = trim($ip_address_and_freq[1]);
		if (not defined $host_name or $host_name eq "") {
			$host_name = $ip_address; 
		} else {
			my @sub_domains = split(/\./, $host_name);
			my $total_dots = scalar @sub_domains - 1;
			if($total_dots > 1) {
				shift(@sub_domains);
			} 
			$host_name = join('.', @sub_domains);
		}
		push @{$domain_name_to_ip_addresses{$host_name}}, "dst ip " . $ip_address;
		if(exists $domain_name_to_bytes{$host_name}) {
			$domain_name_to_bytes{$host_name} += $frequency;
		} else {
			$domain_name_to_bytes{$host_name} = $frequency;
		}
	}
	my $topNDomains = 10;
	my %domain_to_array_of_bytes;
	foreach my $domain_name (sort { $domain_name_to_bytes{$b} <=> $domain_name_to_bytes{$a} } keys %domain_name_to_bytes) {
		my $ip_filter = join(" or ", @{$domain_name_to_ip_addresses{$domain_name}});
		foreach my $date_point (@dates_of_interest) {
			my $cyear = sprintf("%02d", $date_point->year());
			my $cmonth = sprintf("%02d", $date_point->month());
			my $cday = sprintf("%02d", $date_point->day());
			my $nfdump_command = "$NfConf::PREFIX/nfdump -M $netflow_sources -N -T  -R ${cyear}/${cmonth}/${cday}/nfcapd.${cyear}${cmonth}${cday}0000:${cyear}/${cmonth}/${cday}/nfcapd.${cyear}${cmonth}${cday}2355 -N -A dstip \"$ip_filter\"  -o csv |  awk 'BEGIN { FS = \",\" } ; {if( NR > 1)  s+=\$13 }; END {print s}'";
			my $a_date_output = `$nfdump_command`;
			$a_date_output = trim($a_date_output);
			push @{$domain_to_array_of_bytes{$domain_name}}, "$a_date_output";
		}
		$topNDomains -= 1;
		last if $topNDomains == 0;
    }

	Nfcomm::socket_send_ok ($socket, \%domain_to_array_of_bytes);
	return 1;
}


#
##
## The Init function is called when the plugin is loaded. It's purpose is to give the plugin 
## the possibility to initialize itself. The plugin should return 1 for success or 0 for 
## failure. If the plugin fails to initialize, it's disabled and not used. Therefore, if
## you want to temporarily disable your plugin return 0 when Init is called.
##
sub Init {
	return 1;
}

#
## The Cleanup function is called, when nfsend terminates. It's purpose is to give the
## plugin the possibility to cleanup itself. It's return value is discard.
sub Cleanup {
}

sub getPatriciaFromDatabase {
	my $trie = new Net::Patricia;
	my $dbh = DBI->connect('dbi:mysql:dest:localhost','dester','passwder')  or die "Connection Error: $DBI::errstr\n";
	my $sth = $dbh->prepare('SELECT cidr,org FROM whoiscache')
                or die "Couldn't prepare statement: " . $dbh->errstr;
	$sth->execute();
	my @data;
	 while (@data = $sth->fetchrow_array()) {
            my $cidr = $data[0];
            my $org = $data[1];
	    $trie->add_string($cidr, $org);
          }
	$dbh->commit;
	$dbh->disconnect;
	return \$trie;
}



# Periodic data processing function
#       input:  hash reference including the items:
#               'profile'       profile name
#               'profilegroup'  profile group
#               'timeslot'      time of slot to process: Format yyyymmddHHMM e.g. 200503031200
sub run {
        my $argref       = shift;
        my $profile      = $$argref{'profile'};
        my $profilegroup = $$argref{'profilegroup'};
        my $timeslot     = $$argref{'timeslot'};
	my $profilepath     = NfProfile::ProfilePath($profile, $profilegroup);
	my %profileinfo     = NfProfile::ReadProfile($profile, $profilegroup);
	my $all_sources     = join ':', keys %{$profileinfo{'channel'}};
	my $netflow_sources = "$NfConf::PROFILEDATADIR/$profilepath/$all_sources";
	my $dbh = DBI->connect('dbi:mysql:dest:localhost','dester','passwder')  or die "Connection Error: $DBI::errstr\n";
	my $trie = $ { getPatriciaFromDatabase() };
	bless $trie, "Net::Patricia::AF_INET";
	my $read = "";
   	$read = `cat /tmp/nfsen_dest_plugin_ipc.txt 2>/dev/null`;
	$read = trim($read);
	if($read eq $netflow_sources) {
		syslog("info", "Destination plugin is good to go");
	} else {
		my $wrote = `echo "$netflow_sources" > /tmp/nfsen_dest_plugin_ipc.txt`;
		syslog("info", "Destination plugin started setting up.");
	}

	my $ignore_subnets = subnet_matcher qw(
	        10.0.0.0/8
	        172.16.0.0/12
	        192.168.0.0/16
		169.254.0.0/16
    	);


	my $year = substr $timeslot, 0, 4;
	my $month = substr $timeslot, 4, 2;
	my $day = substr $timeslot, 6, 2;


	foreach my $protocol ('tcp', 'udp') {
		my $current_idx = 4;
		foreach my $type ('flows', 'packets' ,'bytes') {
			$current_idx += 2;
			my $nfdump_command = "$NfConf::PREFIX/nfdump -M $netflow_sources  -T  -r ${year}/${month}/${day}/nfcapd.${timeslot} -n 300 -s dstip/bytes -N -o csv -q \"proto $protocol\" | awk 'BEGIN { FS = \",\" } ; { if (NR > 1) print \$5, \$$current_idx }'";
			print $nfdump_command;
			my @nfdump_output = `$nfdump_command`;

			my %domain_name_to_bytes;

			my $sql = 'INSERT INTO whoiscache (cidr, org, added_on) VALUES (?,?,?)';
			my $sth = $dbh->prepare($sql);
			my $total_skipped = 0;

			foreach my $a_line (@nfdump_output) {
				my @ip_address_and_freq = split(" ", $a_line);
				my $arr_size = @ip_address_and_freq;
				next if $arr_size != 2;

				my $ip_address = trim($ip_address_and_freq[0]);
				next if $ignore_subnets->("$ip_address");	

				my $host_name = gethostbyaddr(inet_aton($ip_address), AF_INET);
				my $org_name = $trie->match_string($ip_address);
				if(not defined $org_name or $org_name eq "") {
					my $search_options = ["CIDR","OrgName"];
					my $response = whoisip_query($ip_address, "", $search_options);
					my $cidr = $response->{"CIDR"};
					my $org_name = $response->{"OrgName"};
					$total_skipped += 1;
					next if (
						not defined $cidr or 
						not defined $org_name or 
						"" eq $cidr or
						"" eq $org_name
						 );
					$total_skipped -= 1;
					syslog("info", "ADDING: $cidr");
					if (index($cidr, ',') != -1) {
						my @all_cidrs = split("," , $cidr);
						foreach my $a_cidr (@all_cidrs) {
							$a_cidr = trim($a_cidr);
							$trie->add_string("$a_cidr", "$org_name");
							my @new_row_values = ($a_cidr, $org_name, DateTime::Format::MySQL->format_datetime(DateTime->now));
							$sth->execute(@new_row_values);
						}
					} else {
						my @new_row_values = ($cidr, $org_name, DateTime::Format::MySQL->format_datetime(DateTime->now));
						$sth->execute(@new_row_values);
						$trie->add_string("$cidr", "$org_name");	
					}
				} 
				my $frequency = trim($ip_address_and_freq[1]);
				if (not defined $host_name or $host_name eq "") {
					$host_name = $ip_address; 
				} else {
					my @sub_domains = split(/\./, $host_name);
					my $total_dots = scalar @sub_domains - 1;
					if($total_dots > 1) {
						shift(@sub_domains);
					} 
					$host_name = join('.', @sub_domains);
				}
				$host_name = $org_name;
				if(exists $domain_name_to_bytes{$host_name}) {
					$domain_name_to_bytes{$host_name} += $frequency;
				} else {
					$domain_name_to_bytes{$host_name} = $frequency;
				}
			}

			syslog("info", "SKIPPED: $total_skipped");

			my $sql_i = "INSERT INTO rrdgraph_$protocol\_$type (timeslot,domain,frequency, addedon) VALUES (?,?,?,?)";
			my $sths = $dbh->prepare($sql_i);
			my $topNDomains = 10;
			foreach my $domain_name (sort { $domain_name_to_bytes{$b} <=> $domain_name_to_bytes{$a} } keys %domain_name_to_bytes) {
				my $domain_frequency = $domain_name_to_bytes{$domain_name};
				my @new_row_values = ($timeslot, $domain_name, $domain_frequency, DateTime::Format::MySQL->format_datetime(DateTime->now));
				$sths->execute(@new_row_values);
				last if --$topNDomains == 0;
			}
			$sql = "DELETE FROM rrdgraph_$protocol\_$type WHERE addedon <= (? - INTERVAL 130 MINUTE)";
			$sth = $dbh->prepare($sql);
			my @current_time = (DateTime::Format::MySQL->format_datetime(DateTime->now));
			$sth->execute(@current_time);
		}
	}
	$dbh->disconnect;
} # End of run


1;
