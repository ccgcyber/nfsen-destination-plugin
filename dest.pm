#
package dest;

use strict;
use warnings;
use Socket;
use Sys::Syslog;
use Sys::Syslog qw(:standard :macros);
use Data::Dumper;
use DateTime;
use String::Util 'trim';

our $VERSION = 100;

our % cmd_lookup = (
	"create_graph" => \&CreateGraph,
);


sub CreateGraph {
        my      $socket = shift;
        my      $opts = shift;
       
	my @start_date = split("-", $$opts{'start'});
	my @end_date = split("-", $$opts{'end'});	

	my $sdate = DateTime->new(
		            year => @start_date[0],
			       month => @start_date[1],
				     day => @start_date[2],
			  time_zone  => 'floating',
				 );

	my $edate = DateTime->new(
		            year => @end_date[0],
			       month => @end_date[1],
				     day => @end_date[2],
			  time_zone  => 'floating',
	);
	$edate->add(days=>1);
	my @dates_of_interest;

	for(my $curr_date = $sdate->clone(); DateTime->compare($curr_date, $edate) == -1; $curr_date->add(days=>1)) {
		push @dates_of_interest, $curr_date->clone();
	}

	$edate->subtract(days=>1);
	
	syslog("info", join(", ", @dates_of_interest));

	my $syear = $sdate->year();
	my $smonth = sprintf("%02d", $sdate->month());
	my $sday = sprintf("%02d", $sdate->day());
	my $eyear = $edate->year();
	my $emonth = sprintf("%02d", $edate->month());
	my $eday = sprintf("%02d", $edate->day());

	my $nfdump_command = "$NfConf::PREFIX/nfdump -M /data/nfsen/profiles-data/live/upstream1  -T  -R ${syear}/${smonth}/${sday}/nfcapd.${syear}${smonth}${sday}0000:${eyear}/${emonth}/${eday}/nfcapd.${eyear}${emonth}${eday}2355 -n 100 -s ip/bytes -N -o csv -q | awk 'BEGIN { FS = \",\" } ; { if (NR > 1) print \$5, \$10 }'";

	my @nfdump_output = `$nfdump_command`;
	syslog("info", Dumper(\@nfdump_output));
	my %domain_name_to_bytes;
	my %domain_name_to_ip_addresses;

	foreach my $a_line (@nfdump_output) {
		my @ip_address_and_freq = split(" ", $a_line);
		my $arr_size = @ip_address_and_freq;
		if ($arr_size != 2) { 
			next;
		}
		my $ip_address = trim(@ip_address_and_freq[0]);
		my $host_name = gethostbyaddr(inet_aton($ip_address), AF_INET);
		my $frequency = trim(@ip_address_and_freq[1]);
		if (not defined $host_name or $host_name eq "") {
			$host_name = $ip_address; 
		} else {
			my @sub_domains = split(/\./, $host_name);
			my $total_dots = scalar @sub_domains;
			if($total_dots > 2) {
				$host_name = @sub_domains[-2].".".@sub_domains[-1];
			} 
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
			my $nfdump_command = "$NfConf::PREFIX/nfdump -M /data/nfsen/profiles-data/live/upstream1 -N -T  -R ${cyear}/${cmonth}/${cday}/nfcapd.${cyear}${cmonth}${cday}0000:${cyear}/${cmonth}/${cday}/nfcapd.${cyear}${cmonth}${cday}2355 -N -A dstip \"$ip_filter\"  -o csv |  awk 'BEGIN { FS = \",\" } ; {if( NR > 1)  s+=\$13 }; END {print s}'";
			my $a_date_output = `$nfdump_command`;
			$a_date_output = trim($a_date_output);
			push @{$domain_to_array_of_bytes{$domain_name}}, "$a_date_output";
		}
		$topNDomains -= 1;
		last if $topNDomains == 0;
    	}

	syslog("info", Dumper(\%domain_to_array_of_bytes));
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
        syslog("info", "demoplugin Cleanup");
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
} # End of run


1;
