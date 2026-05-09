import pages from '@/Routes/pages';
import { lazy } from 'react';

const NavsExamplePage = lazy(() => import('@/pages/examples/userInterface/Navs.example.page'));

const AppPages = [
	{
		path: pages.examples.exampleMain.to,
		children: [
			{
				path: pages.examples.exampleMain.subPages.userInterface.subPages.navs.to,
				element: <NavsExamplePage />,
			},
		],
	},
];

export default AppPages;
