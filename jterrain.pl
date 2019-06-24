#!/usr/bin/perl
use autodie;

open my $fh, '<raw:', "$ARGV[0]";
open my $of1, '>HeightMap.raw';
open my $of2, '>BlockMapA.raw';
open my $of3, '>BlockMapB.raw';
open my $of4, '>DustMap.raw';

sub debugof
{
	print "export to @_[0]\n";
	print tell @_[1];
	print "\n";
}

sub extractTerrain
{
	$if = @_[0];
	$of = @_[1];
	$ln = @_[2];
	
	my $bytes_read = read $if, my $bytes, $ln;
	die 'Got $bytes_read but expected $ln' unless $bytes_read == $ln;
	print $of $bytes;
	# debugof $if, $of;
}

print "Extract raw map: HeightMap\n";
extractTerrain $fh, $of1, 0x40000;
print "Extract raw map: BlockMapA\n";
extractTerrain $fh, $of2, 0x20000;
print "Extract raw map: BlockMapB\n";
extractTerrain $fh, $of3, 0x20000;
print "Extract raw map: DustMap\n";
extractTerrain $fh, $of4, 0x80000;

close $fh;
close $of1;
close $of2;
close $of3;
close $of4;
