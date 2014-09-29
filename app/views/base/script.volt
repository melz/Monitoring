#!/bin/bash
#Copyright Loading Deck Limited. All rights reserved.
#Version: 0.4

KEY='{{ key }}'
PASS='{{ pass }}'
POST_URL='{{ host }}'

LAST_STAT_FILENAME="/tmp/$(basename ${0}).save"
rm -f "${LAST_STAT_FILENAME}.new"

function collect() {
	local JSON_DATA='{"system":{';	#data collected in JSON format
	#memory used
	MEM_USED=$(free -m | sed 's/[ ]\+/ /g' | grep 'Mem:' | cut -f 3 -d' ')
	MEM_CACHED=$(free -m | sed 's/[ ]\+/ /g' | grep 'Mem:' | cut -f 7 -d' ')
	MEM_FREE=$(free -m | sed 's/[ ]\+/ /g' | grep 'Mem:' | cut -f 4 -d' ')

	JSON_DATA+="\"mem_used\":\"${MEM_USED}\"";
	JSON_DATA+=",\"mem_cached\":\"${MEM_CACHED}\"";
	JSON_DATA+=",\"mem_free\":\"${MEM_FREE}\"";

	LOAD_5MIN=$(cat /proc/loadavg | cut -f2 -d' ')
	JSON_DATA+=",\"load_5min\":\"${LOAD_5MIN}\"";


	JSON_DATA+=',"disk":{'
	COMMA=''
	#Note: Disk access needs permissions!
	for disk_info in $(df -P | grep '^/dev/' | sed 's/[ ]\+/,/g'); do
		DISK_NAME=$(echo "${disk_info}" | cut -f1 -d, | cut -f3 -d'/')

		INODES_INFO=$(df -iP | grep "${DISK_NAME}" | sed 's/[ ]\+/,/g');

		TOTAL_INODES=$(echo "${INODES_INFO}" | cut -f2 -d,)
		FREE_INODES=$(echo "${INODES_INFO}" | cut -f4 -d,)
		TOTAL_INODES=$(echo "$TOTAL_INODES/1024" | bc)	#inodes are in Kilo
		FREE_INODES=$(echo "$FREE_INODES/1024" | bc)

		TOTAL_GB=$(echo "${disk_info}" | cut -f2 -d,)	#size is in Kb
		FREE_GB=$(echo "${disk_info}" | cut -f4 -d,)
		TOTAL_GB=$(echo "$TOTAL_GB/(1024)" | bc)	#size in Mb
		FREE_GB=$(echo "$FREE_GB/(1024)" | bc)

		SECTOR_SIZE=$(parted -sm /dev/${DISK_NAME} print | grep "/dev/${DISK_NAME}" | cut -f4 -d:)

		IO_INFO=$(vmstat -d | sed 's/[ ]\+/,/g' | grep "${DISK_NAME:0:3}")
		CUR_DISK_READS=$(echo "${IO_INFO}" | cut -f4 -d,)
		CUR_DISK_WRITES=$(echo "${IO_INFO}" | cut -f8 -d,)

		if [ -f "${LAST_STAT_FILENAME}" ]; then
			LAST_DISK_READS=$(grep "${DISK_NAME}_sectors_read" "${LAST_STAT_FILENAME}" | cut -f2 -d=)
			LAST_DISK_WRITES=$(grep "${DISK_NAME}_sectors_write" "${LAST_STAT_FILENAME}" | cut -f2 -d=)
		fi

		if [ "${LAST_DISK_READS}" ]; then
			DISK_READS=$(echo "($CUR_DISK_READS-$LAST_DISK_READS)*$SECTOR_SIZE/1024" | bc)
			DISK_WRITES=$(echo "($CUR_DISK_WRITES-$LAST_DISK_WRITES)*$SECTOR_SIZE/1204" | bc)
		else
			DISK_READS='-1'
			DISK_WRITES='-1'
		fi
		echo -e "${DISK_NAME}_sectors_read=${CUR_DISK_READS}\n${DISK_NAME}_sectors_write=${CUR_DISK_WRITES}" >> "${LAST_STAT_FILENAME}.new"


		JSON_DATA+="${COMMA}\"${DISK_NAME}\":{"

		JSON_DATA+="\"write_kb\":\"${DISK_WRITES}\""
		JSON_DATA+=",\"read_kb\":\"${DISK_READS}\""

		JSON_DATA+=",\"total_inodes_k\":\"${TOTAL_INODES}\""
		JSON_DATA+=",\"free_inodes_k\":\"${FREE_INODES}\""

		JSON_DATA+=",\"total_space_mb\":\"${TOTAL_GB}\""
		JSON_DATA+=",\"free_space_mb\":\"${FREE_GB}\""

		JSON_DATA+="}"
		COMMA=','
	done
	JSON_DATA+='}'

	JSON_DATA+=",\"iface\":{"
	COMMA=''
	#for each interface
	for IFACE_NAME in $(cat /proc/net/dev | sed -n '3,$p' | cut -f1 -d: | sed 's/^[ ]\+//g'); do
		iface_stat=$(grep "${IFACE_NAME}" /proc/net/dev | cut -f2 -d: | sed 's/^[ ]\+//g' | sed 's/[ ]\+/,/g')

		CUR_RX=$(echo "${iface_stat}" | cut -f1 -d,)
		CUR_TX=$(echo "${iface_stat}" | cut -f9 -d,)

		if [ -f "${LAST_STAT_FILENAME}" ]; then
			LAST_RX=$(grep "${IFACE_NAME}_rx" "${LAST_STAT_FILENAME}" | cut -f2 -d=)
			LAST_TX=$(grep "${IFACE_NAME}_tx" "${LAST_STAT_FILENAME}" | cut -f2 -d=)
		fi

		if [ "${LAST_RX}" ]; then
			IFACE_RX=$(echo "($CUR_RX-$LAST_RX)/(1024*1024)" | bc)
			IFACE_TX=$(echo "($CUR_TX-$LAST_TX)/(1024*1024)" | bc)
		else
			IFACE_RX='-1'
			IFACE_TX='-1'
		fi
		echo -e "${IFACE_NAME}_rx=${CUR_RX}\n${IFACE_NAME}_tx=${CUR_TX}" >> "${LAST_STAT_FILENAME}.new"

		JSON_DATA+="${COMMA}\"${IFACE_NAME}\":{"

		JSON_DATA+="\"ingress_mb\":\"${IFACE_RX}\""
		JSON_DATA+=",\"egress_mb\":\"${IFACE_TX}\""
		JSON_DATA+="}"
		COMMA=','
	done
	JSON_DATA+='}'

	mv "${LAST_STAT_FILENAME}.new" "${LAST_STAT_FILENAME}"

	JSON_DATA+="}}"

	echo "${JSON_DATA}"
}

JSON_STAT=$(collect);

#post data to server
echo "${JSON_STAT}" > /tmp/stat.json
wget -O /dev/null --no-check-certificate --post-file /tmp/stat.json ${POST_URL}?key=${KEY}\&pass=${PASS}