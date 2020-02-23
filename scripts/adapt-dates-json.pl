#!/usr/bin/env perl

my $json = shift // 0;
my $startdate = shift // 0;
my $enddate = shift // 0;

if ( ! $json  || ! $startdate || ! $enddate ) {
	die;
}

open( FILE, "<", $json ) or die "No $json";

while ( <FILE> ) {
	if ( $_=~/\"startdate\":\s*\"(\d+.*)\"\,/ ) {
		print "\t\"startdate\": \"$startdate\",\n";
	}

	elsif ( $_=~/\"enddate\":\s*\"(\d+.*)\"\,/ ) {
		print "\t\"enddate\": \"$enddate\",\n";
		
	}
	
	else {
		print;
	}
	
}

close( FILE );