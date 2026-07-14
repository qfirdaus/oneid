"use strict";

module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
	jshint: {
		options: {
			jshintrc: '.jshintrc',
			reporter: require('jshint-stylish')
		},
		all: {
			src: [
				'Gruntfile.js',
			]
		}
	},
    sass: {                              // Task 
		dist: {                            // Target 
		  options: {                       // Target options 
			style: 'expanded'
		  },
		  files: {                         
			'public/dist/css/style.css': 'src/scss/style.scss',       // 'destination': 'source'
		  }
		}
    },
	
	watch: {
        src: {
		files: ['src/scss/style.scss'],
            tasks: ['sass:dist'],
            options: {
                spawn: false,
				livereload: 12344
            }
		} 		
    },
	 connect: {
		server: {
		  options: {
			port: 9000,
			hostname: '0.0.0.0',
			base: 'public',
			open:true
		  }
		}
	},
	  
});

  // Load all plugins.
  grunt.loadNpmTasks('grunt-contrib-jshint');
  grunt.loadNpmTasks('grunt-contrib-sass');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-connect');
  
  // Register all Tasks.
  grunt.registerTask('build',  ['sass:dist','jshint:all']);
  grunt.registerTask('serve',  ['build','connect:server','watch:src']);
  grunt.registerTask('default',  ['build']);
  grunt.registerTask('sass-compile',  ['build']);
};
