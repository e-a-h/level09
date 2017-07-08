# Put this file in your maya scripts directory, then paste the following
# into the "Python" command line (lower-right, if it says MEL, click it
# to change to Python mode):

# import VertPositions; reload(VertPositions); VertPositions.hexdumpshelfbutton();

# A shelf button will be created in the "Custom" shelf.
# Select objects and then click the button! Hex-formated vert positions
# will be dumped into a text file along with UIDs

import maya.OpenMaya as OpenMaya
import maya.cmds as cmds
import textwrap
import re
import maya.mel as mel
from pymel.core import *

def getVertPositions(  ):

    # get the active selection
    selection = OpenMaya.MSelectionList()
    OpenMaya.MGlobal.getActiveSelectionList( selection )
    iterSel = OpenMaya.MItSelectionList(selection, OpenMaya.MFn.kMesh)

    # go through selection
    while not iterSel.isDone():

        # get dagPath
        dagPath = OpenMaya.MDagPath()
        iterSel.getDagPath( dagPath )

        # create empty point array
        inMeshMPointArray = OpenMaya.MPointArray()

        # create function set and get points in world space
        currentInMeshMFnMesh = OpenMaya.MFnMesh(dagPath)
        currentInMeshMFnMesh.getPoints(inMeshMPointArray, OpenMaya.MSpace.kWorld)

        # put each point to a list
        pointList = []

        for i in range( inMeshMPointArray.length() ) :

            pointList.append( [inMeshMPointArray[i][0], inMeshMPointArray[i][1], inMeshMPointArray[i][2]] )
            #print str(inMeshMPointArray[i][0]) +" "+ str(inMeshMPointArray[i][1]) +" "+ str(inMeshMPointArray[i][2]) +" 0"

        return pointList

def hexdumpshelfbutton():
    command = "import VertPositions; reload(VertPositions); VertPositions.hexdump();"
    shelfButton( rpt=1, i1="pythonFamily.png", l=command, ann="HEXY", p="Custom" );

def hexdump( fulloutput=0 ):
    #get selection
    selectedThings = cmds.ls(sl=1)
    if not selectedThings:
        cmds.error( "Nothing selected", noContext=1 )
        return

    #Choose a text file
    fileName = fileDialog2( dialogStyle=2, caption="Hexdump Vertex Positions", fileFilter="*.txt", cancelCaption="Piss Off", okCaption="Rock On!" )
    if not fileName:
        warning( "No File Selected" )
        return

    print fileName[0]
    fileWrite = open(fileName[0], 'w')
    hexarray = {}

    #iterate selected
    for obj in selectedThings:
        sn = re.sub( '_Mesh', '', obj )
        cmds.select(obj, r=1)
        vp = getVertPositions()
        final = ""
        for a in vp:
            for b in a:
                final += str( b )
                final += " "
            final += "0 "
        final = final.rstrip()
        hex = mel.eval( "HexifyFloats(\""+final+"\","+str(fulloutput)+");" )
        if fulloutput:
            print ""
            print "echo \"\" >> dump"
            print "echo \"" + sn + "\" >> dump"
            print "echo \"" + final + "\" >> dump"
            print "echo \"\" >> dump"
            print ""
        #add to the list
        hexarray[sn] = hex
        #print hex, split into 32-character lines for easy copy/paste
        print "Exporting " + sn + "..."
        wrapped = textwrap.fill( hex, 32 )
        fileWrite.write( sn+"\n" )
        fileWrite.write( wrapped )
        fileWrite.write( "\n\n" )
    fileWrite.close()

    cmds.select( selectedThings, r=1 )
    # at some point return may be useful
    #return hexarray
