#!/bin/bash

CONFDIR=$1
OUTDIR=$2
CONFILE=$3
TASK=$4

#langs=("ca" "en" "es" "fr" "ro")
langs=("ca")

for LANG in "${langs[@]}"
do
	mkdir -p $OUTDIR
	mkdir -p $OUTDIR/$LANG
	php retrieveUsers.php $CONFDIR/retrieveUsers.$LANG.json > $OUTDIR/$LANG.list
	while IFS= read -r var
	do
		echo $var
		php query.php $CONFILE "$var" $TASK > "$OUTDIR/$LANG/$var.csv"
	done < "$OUTDIR/$LANG.list"

done
