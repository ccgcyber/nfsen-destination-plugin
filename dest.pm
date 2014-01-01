#
##
## Name of the plugin
package dest;
#
## highly recommended for good style Perl programming
use strict;
use Sys::Syslog;
use Sys::Syslog qw(:standard :macros);

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
	
        syslog("info", "CREATE GRAPH RAN");
	my %args;
        Nfcomm::socket_send_ok ($socket, \%args);
	syslog("info", "LUHMAO");
	syslog("info", $start_date.$end_date);
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
