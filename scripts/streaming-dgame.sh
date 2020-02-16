#!/bin/bash

# To be put in cron every 3 days
# 22 22 */3 * * *

ACT=$(date -d "-7 days" +%Y-%m-%dT00:00:00Z)

timeout 3h sh -c "while true; do curl -o - \"https://stream.wikimedia.org/v2/stream/revision-create?since=$ACT\" |grep 'wikidata.org' | sed 's/^data: //g' | grep 'Distributed Game' ; done >\"wikidata_dg_$(date +%Y-%m-%d_%T)\""

