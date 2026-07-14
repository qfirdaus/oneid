var options = {
	chart: {
		height: 350,
		type: "bar",
		toolbar: {
			show: false,
		},
	},
	plotOptions: {
		bar: {
			horizontal: false,
			columnWidth: "60%",
			distributed: true,
			borderRadius: 4,
		},
	},
	dataLabels: {
		enabled: false,
	},
	stroke: {
		show: true,
		width: 2,
		colors: ["transparent"],
	},
	series: [
		{
			name: "Revenue",
			data: [3000, 6000, 9000, 12000],
		},
	],
	legend: {
		show: false,
	},
	xaxis: {
		categories: ["Q1", "Q2", "Q3", "Q4"],
	},
	yaxis: {
		show: false,
	},
	fill: {
		opacity: 1,
	},
	tooltip: {
		y: {
			formatter: function (val) {
				return "$" + val;
			},
		},
	},
	grid: {
		show: false,
		xaxis: {
			lines: {
				show: true,
			},
		},
		yaxis: {
			lines: {
				show: false,
			},
		},
		padding: {
			top: 0,
			right: 0,
			bottom: -10,
			left: 0,
		},
	},
	colors: ["#60b4bb", "#83d0d7", "#a8e0e6", "#daf3f6"],
};
var chart = new ApexCharts(document.querySelector("#earningsGraph"), options);
chart.render();
