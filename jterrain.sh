#!/bin/sh

#####################################
#
# This file, quickbms.exe, and JourneyTerrain.bms must all be copied to
# your ~/bin directory.
# TODO: refactor to use PHP instead of quickbms for non-windows friends
#
# EXTRACT USAGE: jterrain.sh -x FolderNameToBackupInto
# With the -x flag, specify a folder to copy the extracted data into.
# You can then edit the extracted tif files. This mode also creates a
# timestamped copy of the extracted files in case you want to revert later
# 
# IMPORT USAGE: jterrain.sh -r RestoreFromThisFolderName
# Flag "-r" sets re-import mode.
# Argument is a folder from which to import. The folder does NOT need to
# have all 4 textures. If any files are missing, then those sections of
# TerrainData won't be overwritten.
# 
#####################################

ModDir="$2"
BackupDir=""
StartDir=`pwd`
ScriptPath=`cygpath -m -p ~/bin/JourneyTerrain.bms`
BMS_Exe=~/bin/quickbms.exe
SelfName=`basename "$0"`

RunMode=0
ExtractMode=1
ReimportMode=2

bold=$(tput bold)
bel=$(tput bel)
smul=$(tput smul)
blink=$(tput blink)
normal=$(tput sgr0)
smso=$(tput smso)
red=$(tput setab 1)
c2=$(tput setab 2)
c3=$(tput setab 3)
c4=$(tput setab 4)
c5=$(tput setab 5)
c6=$(tput setab 6)
blacktext=$(tput setaf 0)
warn=$red$bold$bel

ExportUsage="${bold}Export Usage:${normal} $SelfName -x ExtractDir"
ImportUsage="${bold}Import Usage:${normal} $SelfName -r ModifiedFileDir"

if [ $# == 0 ]; then
  echo "Wrong number of arguments ($#)" >&2
  echo $ExportUsage >&2
  echo $ImportUsage >&2
  exit 1
fi

ValidateSourceFile ()
{
  # Check for TerrainData file, unzip if necessary, otherwise exit.
  if [ -f TerrainData.bin.gz ]; then
    gunzip -kf TerrainData.bin.gz
  else
    echo "Could not find TerrainData.bin.gz file in ${bold}$StartDir${normal}" >&2
    exit 1
  fi
}

ValidateDir ()
{
  ValidDir=""
  # argument count is 1 AND
  # argument is not empty string AND
  # { argument is existing dirname OR mkdir succeeds }
  if [ ! -z "$1" ] && { [ -d "$1" ] || mkdir "$1"; }; then
    ValidDir=`realpath "$1"`
  else
    # Don't do anything else if this fails
    echo "Could not validate directory from input \"$1\"" >&2
    exit 1
  fi
  # echo so caller can capture validated dirname
  echo $(printf '%q' "$ValidDir" )
}

CleanupTempFiles ()
{
  StartedIn=`pwd`
  echo "Removing temp files..." >&2
  if [ -f $StartDir/TerrainData.bin ]; then
    rm $StartDir/TerrainData.bin
  fi
  if [ -d "$ModDir" ]; then
    cd "$ModDir"
    if [ -f BlockMapA.raw ]; then
      rm BlockMapA.raw
    fi
    if [ -f BlockMapB.raw ]; then
      rm BlockMapB.raw
    fi
    if [ -f DustMap.raw ]; then
      rm DustMap.raw
    fi
    if [ -f HeightMap.raw ]; then
      rm HeightMap.raw
    fi
    cd $StartedIn
  fi
}

RunExtract ()
{
  if [ $# != 1 ]; then
    echo "${warn}That's not right...${normal}" >&2
    exit 1
  fi
#$( ValidateDir "$1" )
  ExtractDir="$1"
  echo "Starting from $StartDir" >&2
  echo "Extracting to ${ExtractDir}" >&2

  printf "\n${c4}> $BMS_Exe $ScriptPath TerrainData.bin $ExtractDir${normal} \n" >&2
  $BMS_Exe $ScriptPath TerrainData.bin $ExtractDir >&2

  cd "$ExtractDir"

  if [ -e BlockMapA.tif ] ||
     [ -e BlockMapB.tif ] ||
     [ -e DustMap.tif ] ||
     [ -e HeightMap.tif ]; then
    printf "\n${warn}DANGER!!!${normal} One or more target files exist and I refuse to clobber them!\n" >&2
    printf "Choose a different extract directory, or delete some files.\n\n" >&2

    CleanupTempFiles
  else
    convert -depth 8 -size 256x512 gray:BlockMapA.raw BlockMapA.tif
    convert -depth 8 -size 256x512 gray:BlockMapB.raw BlockMapB.tif
    convert -depth 8 -size 256x512 -define tiff:alpha=unspecified RGBA:DustMap.raw -type TrueColorMatte tif:DustMap.tif
    convert -depth 16 -size 256x512+0 -endian MSB gray:HeightMap.raw HeightMap.tif

    CleanupTempFiles

    cd "$StartDir"
    # Set backup location
    BackupDir=$( ValidateDir "$StartDir/BackupTerrainData_"`date +%Y%m%d_%s` )
    echo "Backing up to $BackupDir" >&2

    cp $ExtractDir/* $BackupDir
    if [ -z "$(ls -A "$BackupDir")" ]; then
      # Remove backupdir if it's empty at this point
      rmdir $BackupDir
    fi
  fi

  cd "$StartDir"
}

RunReimport ()
{ 
  if [ $# != 1 ]; then
    echo $ImportUsage >&2
  fi

  ExtractDir="$1"
  echo "Starting from $StartDir" >&2
  echo "Reimporting from ${ExtractDir}" >&2

  if [ ! -d "$ExtractDir" ]; then
    echo "Cannot restore from nonexistent directory \"$ExtractDir\"" >&2
    CleanupTempFiles
    exit 1
  fi

  cd "$ExtractDir"

  # Convert
  MODCOUNT=0
  if [ -f BlockMapA.tif ]; then
    stream -map i -depth 8 BlockMapA.tif BlockMapA.raw
    ((MODCOUNT++))
  fi
  if [ -f BlockMapB.tif ]; then
    stream -map i -depth 8 BlockMapB.tif BlockMapB.raw
    ((MODCOUNT++))
  fi
  if [ -f DustMap.tif ]; then
    stream -map rgba -storage-type char DustMap.tif DustMap.raw
    ((MODCOUNT++))
  fi
  if [ -f HeightMap.tif ]; then
    convert -depth 16 -endian MSB HeightMap.tif HeightMap.gray
    mv HeightMap.gray HeightMap.raw
    ((MODCOUNT++))
  fi

  if [ $MODCOUNT == 0 ]; then
    echo "${warn} WARNING! ${normal} No files have been restored." >&2
  else
    cd $StartDir
    echo "Restoring from $ExtractDir" >&2
    printf "\n${c4}> $BMS_Exe -w -r $ScriptPath TerrainData.bin $ExtractDir${normal} \n" >&2

    $BMS_Exe -w -r $ScriptPath TerrainData.bin $ExtractDir
    gzip -kf TerrainData.bin
  fi
  CleanupTempFiles
}


#####################################
# END function definitions.
# Now get on with running the script:
#####################################

# Get flags/options
OpDir=""
while getopts ":r:x:" opt; do
  case $opt in
    r)
      RunMode=$ReimportMode
      OpDir=$OPTARG
      ;;
    x)
      RunMode=$ExtractMode
      OpDir=$OPTARG
      ;;
    :  ) echo "Option -$OPTARG requires an argument." >&2; exit 1;;
    \? ) echo "Invalid option: -$OPTARG" >&2; exit 1;;
    *  ) echo "What goes here?" >&2; exit 1;;
  esac
done

# Can't do anything without TerrainData.bin file
ValidateSourceFile
StartDir=$( ValidateDir "$StartDir" )
ModDir=$( ValidateDir "$OpDir" )

#choose a mode and run it
case $RunMode in
  $ExtractMode)
    printf "\n${c2} E X T R A C T ${normal}\n\n" >&2
    RunExtract "$OpDir"
    ;;
  $ReimportMode)
    printf "\n${c6} R E I M P O R T ${normal}\n\n" >&2
    RunReimport "$OpDir"
    ;;
  0)
    echo "Mode is not set. use -r (re-import) or -x (extract) flag" >&2
    ;;
esac

printf "\n${blacktext}${red} A ${c2} l ${c3} l ${c4}   ${c5} d ${c6} o ${red} n ${c2} e ${c3} ! ${normal}\n\n" >&2
