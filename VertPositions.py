import maya.OpenMaya as OpenMaya
import maya.cmds as cmds
 
def particleFillSelection(  ):

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

import re
selectedThings = cmds.ls(sl=1)
for obj in selectedThings:
    sn = re.sub( '_Mesh', '', obj )
    print "echo \"" + sn + "\" >> dump"
    print "echo \"\" >> dump"
    cmds.select(obj, r=1)
    vp = particleFillSelection()
    final = ""
    for a in vp:
        for b in a:
            final += str( b )
            final += " "
        final += "0 "
    final = final.rstrip()
    print "php Converter.php -f \"" + final + "\" >> dump"