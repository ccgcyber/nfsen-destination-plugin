#
##
## Name of the plugin
package dest;
#
## highly recommended for good style Perl programming
use strict;
use warnings;
use Socket;
use Sys::Syslog;
use Sys::Syslog qw(:standard :macros);
use Data::Dumper;

#
## This string identifies the plugin as a version 1.3.0 plugin.
our $VERSION = 130;

our % cmd_lookup = (
	"create_graph" => \&CreateGraph,
);


sub CreateGraph {
        my      $socket = shift;
        my      $opts = shift;
       
	my $start_date = $$opts{'start'};
	my $end_date = $$opts{'end'};	

	my $nfdump_command = "nfdump -M /data/nfsen/profiles-data/live/upstream1  -T  -R 2014/01/01/nfcapd.201401011235:2014/01/01/nfcapd.201401011625 -n 100 -s ip/bytes -N -o csv -q | awk 'BEGIN { FS = \",\" } ; { if (NR > 1) print \$5, \$10 }'";

	my %args;
        Nfcomm::socket_send_ok ($socket, \%args);
	my @nfdump_output = `$nfdump_command`;
	my %domain_name_to_bytes;
	foreach my $a_line (@nfdump_output) {
		my @ip_address_and_freq = split(" ", $a_line);
		my $arr_size = @ip_address_and_freq;
		if ($arr_size != 2) { 
			next;
		}
		my $ip_address = @ip_address_and_freq[0];
		my $host_name = gethostbyaddr(inet_aton($ip_address), AF_INET);
		my $frequency = @ip_address_and_freq[1];
		if (not defined $host_name or $host_name eq "") {
			$host_name = $ip_address; 
		} else {
			my @sub_domains = split(/\./, $host_name);
			my $total_dots = scalar @sub_domains;
			if($total_dots > 2) {
				$host_name = @sub_domains[-2].".".@sub_domains[-1];
			} 
		}
		if(exists $domain_name_to_bytes{$host_name}) {
			$domain_name_to_bytes{$host_name} += $frequency;
		} else {
			$domain_name_to_bytes{$host_name} = $frequency;
		}
	}
	syslog("info", "HASH: " . Dumper(\%domain_name_to_bytes));
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
