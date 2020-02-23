#!/bin/bash

# To be put in cron every day
# 22 22 */1 * * *

START="$(date -d "-2 days" +%Y-%m-%d) 00:00:00"
END="$(date -d "-1 day" +%Y-%m-%d) 00:00:00"

JSONFILE=$1

perl adapt-dates-json.pl $JSONFILE $START $END > /tmp/adapted.json 

