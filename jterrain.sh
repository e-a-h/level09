#!/bin/sh

#####################################
#
# EXTRACT USAGE: jterrain.sh FolderNameToExtractTo FolderNameToBackupInto
# Arguments are a folder to extract into, and a folder to copy the extracted
# data into. This way you can save the original, edit the copy, and restore
# either one later.
# 
# IMPORT USAGE: jterrain.sh -r RestoreFromThisFolderName
# Flag "-r" sets re-import mode.
# Argument is a folder from which to import. The folder does NOT need to
# have all 4 textures. If any files are missing, then those sections of
# TerrainData won't be overwritten.
# 
#####################################

EXTRACTDIR=$1
MODIFIED=$2
STARTDIR=`pwd`
CYGHOME=`cygpath -m -p "$HOME"`
SCRIPTPATH="$CYGHOME/bin/JourneyTerrain.bms"
BMS="$CYGHOME/bin/quickbms.exe"

echo ""
echo "Starting from $STARTDIR"
echo ""

if [ "$1" != "-r" ]; then

  echo "Extracting to $EXTRACTDIR/..."
  echo "Backing up to $MODIFIED/..."
  # Extract
  gunzip -k TerrainData.bin.gz
  mkdir $EXTRACTDIR
  $BMS $SCRIPTPATH TerrainData.bin $EXTRACTDIR

  cd $EXTRACTDIR
  convert -depth 8 -size 256x512 gray:BlockMapA.raw BlockMapA.tif
  convert -depth 8 -size 256x512 gray:BlockMapB.raw BlockMapB.tif
  convert -depth 8 -size 256x512 -define tiff:alpha=unspecified RGBA:DustMap.raw -type TrueColorMatte tif:DustMap.tif
  convert -depth 16 -size 256x512+0 -endian MSB gray:HeightMap.raw HeightMap.tif
  cd $STARTDIR

  # TODO: check if backup folder exists and warn/exit if so.
  # We don't want to overwrite good work
  if [ $# = 2 ]; then
    cp -R $EXTRACTDIR $MODIFIED
  fi

  exit 1
else
  echo "Restoring from $MODIFIED/..."
  # Restore
  cd $MODIFIED
  #TODO: check for existence of each component and skip if not
  if [ -f BlockMapA.tif ]; then
    stream -map i -depth 8 BlockMapA.tif BlockMapA.raw
  fi
  if [ -f BlockMapB.tif ]; then
    stream -map i -depth 8 BlockMapB.tif BlockMapB.raw
  fi
  if [ -f DustMap.tif ]; then
    stream -map rgba -storage-type char DustMap.tif DustMap.raw
  fi
  if [ -f HeightMap.tif ]; then
    convert -depth 16 -endian MSB HeightMap.tif HeightMap.gray
  fi
  mv HeightMap.gray HeightMap.raw

  # TODO: check for existence of TerrainData.bin file and warn/exit if not
  cd $STARTDIR
  $BMS -w -r $SCRIPTPATH TerrainData.bin $MODIFIED
  gzip -k TerrainData.bin

  exit 1
fi
