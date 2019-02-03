#!/bin/bash
input=$1
conf=$2
task=$3
outdir=$4

PROG=php query.php
while IFS= read -r var
do
  $PROG	$conf "$var" $task > $outdir/$var.csv
done < "$input"
