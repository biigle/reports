angular.module("dias.projects").controller("ExportController",["$scope","Report","msg","PROJECT",function(e,t,r,o){"use strict";var s=!1,a={basic:t.getBasic,extended:t.getExtended,full:t.getFull,"image label":t.getImageLabel},c=function(){s=!0},l=function(e){s=!1,r.responseError(e)};e.selected={type:"basic"},e.requestReport=function(){e.selected.type&&a[e.selected.type]({project_id:o.id},{},c,l)},e.isRequested=function(){return s}}]),angular.module("dias.projects").factory("Report",["$resource","URL",function(e,t){"use strict";return e(t+"/api/v1/projects/:project_id/reports/:type",{},{getBasic:{method:"POST",params:{type:"basic"}},getExtended:{method:"POST",params:{type:"extended"}},getFull:{method:"POST",params:{type:"full"}},getImageLabel:{method:"POST",params:{type:"image-labels"}}})}]);