# run via python command line or script window.
# Select modified object, then type:
# HexifyFloats()
# You will see a dump of UID followed by hex representation of new vertex
# positions ready to be pasted back into source file.

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
import maya.mel as mel

def HexifyFloats( fulloutput=0 ):
	#get selection
	selectedThings = cmds.ls(sl=1)
	hexarray = {}

	#iterate selected
	for obj in selectedThings:
		sn = re.sub( '_Mesh', '', obj )
		cmds.select(obj, r=1)
		vp = particleFillSelection()
		final = ""
		for a in vp:
			for b in a:
				final += str( b )
				final += " "
			final += "0 "
		final = final.rstrip()
		print sn
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
		print hex
	cmds.select( selectedThings, r=1 )
	# at some point return may be useful
	#return hexarray
